<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Tests;

use Bond\Normality;
use Bond\Normality\MatchRelation\Closure;
use Bond\Normality\Options;
use Bond\Normality\UnitTest\Register\EntityManager as EMR;

use Bond\Pg\Catalog;
use Bond\Pg\Result;

use Bond\Sql\Raw;
use Bond\Sql\Query;

class NormalityProvider extends \Bond\Tests\EntityManagerProvider
{

    /**
     * Bool to check to see if some other class hasn't already run the normality code
     * @var bool
     */
    private static $normalityProvided = false;

    /**
     * A reference to the Bond\Pg that is contained in the EntityManager
     * @var \Bond\Pg
     */
    public $db;

    /**
     * Build the entities and repositories
     */
    public static function setupBeforeClass()
    {

        $container = parent::setupBeforeClass();

        if( self::$normalityProvided ) {
            return $container;
        }
        self::$normalityProvided = true;

        if( !$container ) {
            $container = static::getContainerFromAnnotations()->getcontainer();
        }

        // normality build
        $catalog = new Catalog( $container->get('dbRw') );
        $options = new Options( $catalog, __DIR__.'/../../../' );

        $options->setPath(
            array(
                'entity' => '/Bond/Normality/UnitTest/Entity/Normality',
                'entityPlaceholder' => '/Bond/Normality/UnitTest/Entity',
                'repository' => '/Bond/Normality/UnitTest/Repository/Normality',
                'repositoryPlaceholder' => '/Bond/Normality/UnitTest/Repository',
                'pgRecordConverter' => '/Bond/Normality/UnitTest/PgRecordConverter',
                'register' => '/Bond/Normality/UnitTest/Register',
                'log' => '/Bond/Normality/UnitTest/Logs',
                'backup' => '/Bond/Normality/UnitTest/Backups',
                'entityFileStore' => '/Bond/Normality/UnitTest/EntityFileStore',
            )
        );

        $options->prepareOptions = Options::BACKUP;
        $options->regenerateEntityPlaceholders = true;
        $options->regenerateRepositoryPlaceholders = true;

        $options->matches[] = new Closure(
            function($relation){
                return in_array( $relation->schema, [ 'unit', 'logs', 'common' ] );
            }
        );

        $normality = new Normality($options);

        $built = $normality->build();

        return $container;

    }

    /**
     * Truncate the database, set the database connection
     */
    public function setup()
    {

        parent::setup();

        $this->db = $this->entityManager->db;

        $this->db->query(
            new Raw( <<<SQL
                SELECT build.truncate_schema('unit');
                SELECT build.truncate_schema('logs');
                SELECT build.truncate_schema('common');
SQL
            )
        );

        // register entities with entityManager
        new EMR( $this->entityManager );

        // add all the repos as properties on the object
        foreach( $this->entityManager as $entity => $repo ) {
            $entityName = \Bond\get_unqualified_class( $entity );
            $this->$entityName = $repo;
        }

    }

    /**
     * Utility function. Get the number of rows in a table
     */
    public function getNumRowsInTable($table)
    {
        $query = new Query("SELECT count(*) FROM %table:identifier%");
        $query->table = $table;
        return $this->db->query($query)->fetch(Result::FETCH_SINGLE|Result::TYPE_DETECT);
    }

}