<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Repository;

use Bond\Container;
use Bond\Repository\Multiton;

use Bond\Pg\Result;
use Bond\Pg\Catalog\Relation as EntityRelation;
use Bond\Pg\Catalog\Attribute as Entity;
use Bond\Pg\Catalog\Type as EntityType;

use Bond\Profiler;

use Bond\Sql\Query;

/**
 * Description of Repository
 * @author pete
 */
class Attribute extends Multiton
{

    /**
     * Garbage collection
 * @var int|null
     */
    protected $instancesMaxAllowed = null;

    /**
     * Array of Attribute comments key'd on column key
     * @var array
     */
    protected $comments = null;

    /**
     * @var array
     */
    protected $informationSchema = null;

    /**
     * Preload
     */
    public function __construct()
    {
        call_user_func_array( 'parent::__construct', func_get_args() );
        $this->preload();
    }

    /**
     * {@inheritDoc}
     */
    public function cacheInvalidate( $type = null )
    {
        return 0;
    }

    /**
     * Load the entire schema into ram for speed.
     * @return Container
     */
    public function preLoad()
    {

        $query = new Query( <<<SQL
SELECT
    a.attrelid::text || '.' || a.attnum::text AS key,
    a.attrelid AS attrelid,
    a.attname as name,
    a.atttypid as "typeOid",
    a.attndims <> 0 as "isArray",
    a.attnotnull as "notNull",
    a.attnum as "attnum",
    a.attinhcount as "attinhcount",
    d.adsrc as "default",
    i.character_maximum_length as "length"
FROM
    pg_attribute AS a
INNER JOIN
    pg_type AS t ON t.oid = a.atttypid
INNER JOIN
    pg_class AS c ON c.oid = a.attrelid
LEFT JOIN
    pg_catalog.pg_namespace n ON n.oid = c.relnamespace
LEFT JOIN
    information_schema.columns as i ON n.nspname = i.table_schema AND c.relname = i.table_name AND i.column_name = a.attname
LEFT JOIN
    pg_attrdef AS d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
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

        $attributes = $this->db->query( $query )->fetch();
        parent::cacheInvalidate();
        $this->initByDatas( $attributes );

        // preload references
        $this->preloadReferences();

    }

    /**
     * See, http://www.postgresql.org/docs/8.4/interactive/catalog-pg-constraint.html
     * @return bool. Populate the static variables $this->references $this->isReferencedBy
     */
    private function preloadReferences()
    {

        // References defined in pg_catalog
        $sql = 'SELECT * FROM dev."vAttributeReferences" WHERE "isInherited" = false';

        $relationships = $this->db->query( new Query( $sql ) );

        foreach( $relationships->fetch() as $relationship ) {

            $fk = $this->instancesPersisted[$relationship['fk_key']];
            $pk = $this->instancesPersisted[$relationship['pk_key']];

            if( !$fk or !$pk ) {
                d_pr( $relationship );
                die();
            }

            // slower alternatives. They all do the same thing
            // $fk = $this->persistedGet()->findOneByKey( $relationship['fk_key'] );
            // $pk = $this->persistedGet()->findOneByKey( $relationship['pk_key'] );

            // $fk = $this->find( $relationship['fk_key'] );
            // $pk = $this->find( $relationship['pk_key'] );

            $fk->addReference( $pk );

        }

        // look for normality defined relationships
        $profiler = new Profiler( __FUNCTION__ );
        $profiler->log();

        foreach( EntityRelation::r()->findAll() as $relation ) {

            $tags = $relation->getNormalityTags();
            if( isset( $tags['references'] ) ) {

                foreach( $tags['references'] as $reference ) {

                    $reference = array_map( 'trim', explode( '=', $reference) );

                    $fk = $this->findByRelation( $relation )->findOneByName( $reference[0] );
                    $pk = $this->findByIdentifier( $reference[1] );

                    // go the database to get child records, using the Relation repo methods causes a infinite preload loop
                    $query = new Query(
                        "SELECT oid::text || '.' || %attnum:int%::text FROM dev.relationDescendants( %oid:int%::oid ) as _ ( oid );",
                        array(
                            'attnum' => $pk->get('attnum'),
                            'oid' => $pk->getRelation()->get('oid')
                        )
                    );

                    foreach( $this->db->query( $query )->fetch() as $pk_key ) {

                        $fk->addReference( $this->find( $pk_key ) );

                    }

                }

            }

        }

//        echo $profiler->log()->output();

    }

    /**
     * We don't look things up in the database anymore
     */
    protected function findByFilterComponentsDatabase( array $filterComponents, $source )
    {
        return new Container();
    }

    /**
     * Get relation indexes from a oid
     * @param String $oid
     * @return array()
     */
    public function getIndexes( $oid )
    {

        // load primary keys
        $query = new Query(
            "SELECT string_to_array( indkey::text, ' ') AS keys, * FROM pg_index WHERE indrelid = %oid:int%",
            array( 'oid' => $this->get('oid') )
        );

        $indexes = $this->db->query( $query )->fetch(Result::TYPE_DETECT);

        foreach( $this->indexes as &$index ) {

            $index['columns'] = array();

            foreach( $index['keys'] as $columnIndex ) {
                $index['columns'][] = sprintf(
                    '%s.%s',
                    $oid,
                    $columnIndex
                );
            }

        }

        return $indexes;

    }

    /**
     * Initalise a attribute by a schema.table.column or table.column identifier
     * AFAICT due to the ambiguity of the identifier this is probably best doen with a round trip to the database. It isn't slow.
     * @return
     */
    public function findByIdentifier( $identifier )
    {

        $identifier = explode('.', $identifier);

        if( count( $identifier) < 2 ) {
            throw new \InvalidArgumentException("yeah, not a table.column level identifier");
        }

        $columnName = array_pop( $identifier );
        $tableName = implode('.',$identifier );

        $table = new Query( <<<SQL
SELECT
    a.attrelid::text || '.' || a.attnum AS key
FROM
    pg_attribute AS a
WHERE
    a.attrelid = quote_ident(%tableName:%)::regclass::oid AND
    attname = %columnName:% AND
    a.attnum > 0
SQL
,
            array(
                'tableName' => $tableName,
                'columnName' => $columnName,
            )
        );

        try {
            $columnKey = $this->db->query( $table )->fetch( Result::FETCH_SINGLE );
        } catch( \Exception $e ) {
            return null;
        }

        return $this->find( $columnKey );

    }

    /*
     * Initalise a collection of attributes by their relation.
     *
     * @param int $relationOid. The oid of any relations
     *
     * @return array[Attribute]
     */
    public function findByRelation( $relationOrRelationOid )
    {

        $oid = ( $relationOrRelationOid instanceof EntityRelation )
            ? $relationOrRelationOid->get('oid')
            : $relationOrRelationOid
            ;

        return $this->persistedGet()->findByAttrelid( $oid );

    }

    /**
     * @return array Data array as might be passed to __construct
     */
    public function data( $key )
    {
        throw new \BadMethodCallException("Depreciated");
    }

    /**
     * Get All comment information
     */
    public function getComments()
    {
        $this->buildComments();
        return $this->comments;
    }

    /**
     * It's a hassle complicating all the column source queries with a join to pg_description.
     * This is a lazy load comment solution. Midly hacky.
     */
    protected function buildComments()
    {

        if( is_null( $this->comments ) ) {
            $this->comments = array();
        } else {
            return;
        }

        $sql = <<<SQL
SELECT
    a.attrelid::text || '.' || a.attnum AS key,
    d.description AS text
FROM
    pg_attribute AS a
INNER JOIN
    pg_description AS d ON
        a.attrelid = d.objoid AND
        a.attnum = d.objsubid AND
        d.classoid = 'pg_class'::regclass::oid
WHERE
    a.attnum > 0
;
SQL
;

        $comments = $this->db->query( new Query( $sql ) )->fetch();

        // key the array and populate static cache
        foreach( $comments as $comment ) {
            $this->comments[$comment['key']] = $comment['text'];
        }

    }

}