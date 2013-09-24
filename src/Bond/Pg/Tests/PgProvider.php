<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Tests;

use Bond\Di\DiTestCase;

use Bond\Database\Exception\DatabaseAlreadyExistsException;

use Bond\Normality\DatabaseBuilder;
use Bond\Normality\Exception\AssetChangedException;

use Bond\Pg;
use Bond\Pg\Resource;
use Bond\Pg\Result;

use Bond\Sql\Query;
use Bond\Sql\Raw;

/**
 * @resource ./Di/ConnectionFactoryConfigurable.yml
 * @service testPg
 */
class PgProvider extends DiTestCase
{

    public $connectionFactory;
    public $connectionSettingsRW;
    public $entityManager;

    // have we build the database
    private static $pgProvided = false;

    // Phpunit seems to want at least one test
    public function testNothing()
    {

        $db = $this->connectionFactory->get('RW');

        $query = new Query( <<<SQL
            SELECT
                a.attrelid::text || '.' || a.attnum::text AS key,
                a.attrelid AS attrelid,
                c.relname as "cname",
                a.attname as name,
                a.atttypid as "typeOid",
                a.attndims <> 0 as "isArray",
                a.attnotnull as "notNull",
                a.attnum as "attnum",
                a.attinhcount as "attinhcount",
                d.adsrc as "default",
                i.character_maximum_length as "length",
                description.description as "comment"
            FROM
                pg_attribute AS a
            INNER JOIN
                pg_type AS t ON t.oid = a.atttypid
            INNER JOIN
                pg_class AS c ON c.oid = a.attrelid
            LEFT JOIN
                pg_catalog.pg_namespace n ON n.oid = c.relnamespace
            -- some pgType objects don't report length as you might expect so we need to check information schema
            LEFT JOIN
                information_schema.columns as i ON n.nspname = i.table_schema AND c.relname = i.table_name AND i.column_name = a.attname
            LEFT JOIN
                pg_attrdef AS d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
            LEFT JOIN
                pg_description AS description ON description.objoid = a.attrelid AND description.objsubid = a.attnum
            WHERE
                n.nspname NOT IN ('pg_catalog', 'pg_toast', 'information_schema') AND
                n.nspname = ANY( current_schemas(false) ) AND
                attisdropped = false AND
                attnum > 0
            ORDER BY
                a.attrelid::text ASC,
                a.attnum ASC
SQL
        );
        // print_r( $db->query($query)->fetch(Result::TYPE_DETECT) );
    }

    public static function setupBeforeClass()
    {

        // this a minor speed optimisation
        // don't rely on this because of phpunit's 'run in separate processes thing'
        if (self::$pgProvided) {
            return;
        }

        $container = static::getContainerFromAnnotations()->getcontainer();

        $settingsDb = $container->get('connectionSettingsRW');

        $databaseBuilder = new DatabaseBuilder();
        try {
            $settingsDb = $databaseBuilder->create( $settingsDb, '-' );
        } catch ( DatabaseAlreadyExistsException $e ) {
            $databaseBuilder->truncateDb( $settingsDb );
//            $databaseBuilder->emptyDb( $settingsDb );
        }

        // make a connection to the new database
        $db = new Pg( new Resource( $settingsDb ) );

        try {
            $assets = $databaseBuilder->getSqlAssetsFromDirs(
                $db,
                [
                    __DIR__.'/../../../../database/assets',
                    __DIR__.'/assets',
                ],
                $assetsResolved
            );
        // cleanup
        } catch ( AssetChangedException $e ) {

            // empty the existing database
            $databaseBuilder->emptyDb( $settingsDb );

            unset( $assetsResolved );
            $assets = $databaseBuilder->getSqlAssetsFromDirs(
                $db,
                [
                    __DIR__.'/../../../../database/assets',
                    __DIR__.'/assets',
                ],
                $assetsResolved
            );

            // work around a phpunit bug with serialization
            // don't get me wrong - this is totally stupid and is a duplication of work
            // https://github.com/sebastianbergmann/phpunit/issues/451
            // $newException = new \Exception( $e->getMessage() );
            // $db->resource->terminate();
            // throw $newException;

        }

        $databaseBuilder->resolveByDirs(
            [
                __DIR__.'/assets',
            ],
            $assets,
            $assetsResolved
        );

        $db->resource->terminate();

        self::$pgProvided = true;

        return $container;

    }

    // don't maintain a ton of open database connections
    // close any that are still up and running
    public static function tearDownAfterClass()
    {
        $reflResourceInstances = (new \ReflectionClass('Bond\Pg\Resource'))->getProperty('instances');
        $reflResourceInstances->setAccessible(true);
        foreach( $reflResourceInstances->getValue() as $resource ) {
            $resource->terminate();
        }
    }

    public function getNumberRowsInTable( $relation )
    {

        $db = $this->connectionFactory->get('RW');

        $query = new Query("SELECT count(*) FROM %relation:identifier%");
        $query->relation = $relation;

        return $db->query( $query )
            ->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );

    }

}