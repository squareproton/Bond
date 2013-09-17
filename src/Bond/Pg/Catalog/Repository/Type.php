<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Repository;

use Bond\Repository\Multiton;
use Bond\Sql\Query;
use Bond\Pg\Result;
use Bond\Pg\Catalog\Type as Entity;

/**
 * Description of Repository
 * @author pete
 */
class Type extends Multiton
{

    protected $instancesMaxAllowed = null;

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
    t.oid AS oid,
    t.typname AS name,
    n.nspname as "schema",
    t.typlen AS length,
    t.typtype AS type,
    t.typcategory AS category,
    t.typarray AS "arrayType",
    t.typdefault AS default,
    t.typdefaultbin AS "defaultBin"
FROM
    pg_type AS t
INNER JOIN
    pg_namespace AS n ON n.oid = t.typnamespace
WHERE
    t.oid IN (
        SELECT
            DISTINCT a.atttypid as "typeOid"
        FROM
            pg_attribute AS a
        INNER JOIN
            pg_type AS t ON t.oid = a.atttypid
        INNER JOIN
            pg_class AS c ON c.oid = a.attrelid
        LEFT JOIN
            pg_catalog.pg_namespace n ON n.oid = c.relnamespace
        WHERE
            n.nspname NOT IN ('pg_catalog', 'pg_toast', 'information_schema') AND
            attisdropped = false AND
            attnum > 0
)
;
SQL
        );

        $types = $this->db->query( $query )->fetch();
        parent::cacheInvalidate();
        $this->initByDatas( $types );

    }

    /**
     * Get array of enum options
     * @return array()
     */
    public function getEnumOptions( $oid )
    {

        $query = new Query(
            "SELECT enumlabel FROM pg_enum WHERE enumtypid = %oid:int%",
            array(
                'oid' => $oid
            )
        );

        return $this->db->query( $query )->fetch();

    }

    /**
     * Init a type by it's name. Most likely used for enum types
     * @return Type|null
     */
    public function findByName( $name )
    {

        $result = $this->db->query(
            new Query( <<<SQL
SELECT
    t.oid AS oid,
    t.typname AS name,
    n.nspname as "schema",
    t.typlen AS length,
    t.typtype AS type,
    t.typcategory AS category,
    t.typarray AS "arrayType",
    t.typdefault AS default,
    t.typdefaultbin AS "defaultBin"
FROM
    pg_type AS t
INNER JOIN
    pg_namespace AS n ON n.oid = t.typnamespace
WHERE
    typname = %name:text% OR
    ( n.nspname || '.' || t.typname ) = %name:text%
SQL
,
                array(
                    'name' => $name
                )
            )
        )->fetch( Result::FETCH_SINGLE );

        return $this->initByData( $result );

    }

    /**
     * @return array Data array as might be passed to __construct
     */
    public function data( $oid )
    {

        $db = $this->getDb();

        $result = $db->query(
            new Query( <<<SQL
SELECT
    t.oid AS oid,
    t.typname AS name,
    n.nspname as "schema",
    t.typlen AS length,
    t.typtype AS type,
    t.typcategory AS category,
    t.typarray AS "arrayType",
    t.typdefault AS default,
    t.typdefaultbin AS "defaultBin"
FROM
    pg_type AS t
INNER JOIN
    pg_namespace AS n ON n.oid = t.typnamespace
WHERE
    t.oid = %oid:int%
SQL
                , array( 'oid' => $oid )
            )
        );

        return $result->fetch( Result::FETCH_SINGLE );

    }

}