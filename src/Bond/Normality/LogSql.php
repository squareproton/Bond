<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality;

use Bond\Container;

use Bond\Pg;
use Bond\Pg\Result;
use Bond\Sql\Raw;

use Bond\Pg\Catalog\Relation;
use Bond\Pg\Catalog\Attribute;
use Bond\Pg\Catalog\Type;
use Bond\Pg\Catalog\Index;

use Bond\Normality\LogSql\Table;
use Bond\Normality\LogSql\FnEntityHistory;
use Bond\Normality\LogSql\FnTableAtState;
use Bond\Normality\LogSql\ViewTableHistory;

/**
 * Generator
 *
 * @author pete
 */
class LogSql
{

    /**
     * @var inherits
     */
    private $inherits = null;

    /**
     * @var Array of inherited values
     */
    private $inheritedValues = array();

    /**
     * Relations to work on
     * @var Bond\Entity\Container
     */
    private $working;

    /**
     * Standard __construct.
     *
     * @param Pg $db Source schema
     * @param Relation $inherits The relation our log objects will be inheriting from
     * @param array Array of default values which will be passed to the inheriting funciton
     */
    public function __construct( $schema, Relation $inherits, $inheritedValues )
    {

        $this->inherits = $inherits;
        $this->inheritedValues = $inheritedValues;

        $this->catalogRefresh();

        $this->working = Relation::r()->findAllByRelkindAndSchema( 'r', $schema )
            ->filter(
                // remove log tables and tables with no primary key
                function($relation) use ( $inherits ) {
                    return !( $relation->isLogTable() || $relation->getLogTable($inherits) || $relation->isMaterialisedView() || $relation->getPrimaryKeys()->count() === 0 );
                }
            );
    }

    public function build( Pg $db )
    {

        // iterate over the relations and build the
        foreach( $this->working as $relation ) {

            $table = new Table(
                $relation,
                $this->inherits,
                $this->inheritedValues
            );

            $db->query( $table );

        }

        $this->catalogRefresh();

        // get our new log table relations
        $logRelations = Relation::r()
            ->findAllBySchema( $this->inherits->get('schema') )
            ->findByName( $this->working->pluck('name') );

        // build entity history
        foreach( $logRelations as $relation ) {

            $original = $this->working->findOneByName( $relation->get('name') );

            $fnEntityHistory = new FnEntityHistory( $relation, $original );
            $db->query( $fnEntityHistory );

            $fnTableAtState = new FnTableAtState( $relation, $original );
            $db->query( $fnTableAtState );

            $viewTableHistory = new ViewTableHistory( $relation, $original );
            $db->query( $viewTableHistory );

        }

        return $logRelations;

    }

    private function catalogRefresh()
    {
        Relation::r()->preload();
        Attribute::r()->preload();
        Index::r()->preload();
        Type::r()->preload();
    }

}