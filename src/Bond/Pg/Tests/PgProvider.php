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
use Bond\Pg\Result;
use Bond\Sql\Query;
use Bond\Normality\DatabaseBuilder;
use Bond\Normality\Exception\AssetChangedException;

use Bond\Pg;
use Bond\Pg\Resource;

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