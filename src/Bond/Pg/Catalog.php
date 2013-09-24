<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg;

use Bond\Database\Enum;

use Bond\Pg;
use Bond\Pg\Catalog\Container\PgAttribute;
use Bond\Pg\Catalog\Container\PgClass;
use Bond\Pg\Catalog\Container\PgIndex;
use Bond\Pg\Catalog\Container\PgType;
use Bond\Pg\Result;
use Bond\Sql\Raw;
use Bond\Profiler;

class Catalog
{

    /**
     * The database connection resource
     * Bond\Pg
     */
    private $db;

    /**
     *
     */
    private $enum;

    /**
     * @param Bond\Pg $db
     */
    public function __construct( Pg $db )
    {
        $this->db = $db;
        $this->pgAttributes = new PgAttribute($this);
        $this->pgClasses = new PgClass($this);
        $this->pgIndexes = new PgIndex($this);
        $this->pgTypes = new PgType($this);

        $this->loadReferences();
    }

    /**
     * MagicGetter
     * @return mixed
     */
    public function __get($var)
    {
        switch ($var) {
            case 'db':
                return $this->db;
            case 'enum':
                if( !$this->enum ) {
                    $this->enum = $this->getEnum();
                }
                return $this->enum;
        }
        throw new \Bond\UnknownPropertyForMagicGetter($var);
    }

    /**
     * Get array of enum's and their values from the database
     * @param Bond\Pg
     * @return array
     */
     private function getEnum()
     {

        $enumResult = $this->db->query(
            new Raw( <<<SQL
                SELECT
                    t.typname as name,
                    n.nspname as schema,
                    array_agg( e.enumlabel ORDER BY e.enumsortorder ) as labels
                FROM
                    pg_enum e
                INNER JOIN
                    pg_type t ON e.enumtypid = t.oid
                INNER JOIN
                    pg_namespace n ON t.typnamespace = n.oid
                WHERE
                    n.nspname = ANY( current_schemas(false) )
                GROUP BY
                    t.typname, t.oid, n.nspname
                ORDER BY
                    schema, t.typname ASC
SQL
             )
        );

        $output = [];
        foreach( $enumResult->fetch(Result::FLATTEN_PREVENT | Result::TYPE_DETECT) as $data ) {
            $output[$data['name']] = $data['labels'];
        }

        return new Enum($output);

     }

    /**
     * See, http://www.postgresql.org/docs/8.4/interactive/catalog-pg-constraint.html
     * @return bool. Populate the static variables $this->references $this->isReferencedBy
     */
    private function loadReferences()
    {

        // References defined in pg_catalog
        $sql = new Raw('SELECT * FROM dev."vAttributeReferences" WHERE "isInherited" = false');
        $sql = new Raw( <<<SQL
-- Based on. http://code.google.com/p/pgutils/ but very heavily modified
-- Potentially inheritance aware/compensating. Be careful. Use the unit tests.
WITH "vRelationDescendants" as (
    SELECT
        c.oid,
        c.relname,
        (
            WITH RECURSIVE
            q( oid ) AS
            (
                SELECT
                    crd.oid
                FROM
                    pg_class crd
                WHERE
                    crd.oid = c.oid

                UNION ALL

                SELECT i.inhrelid FROM q INNER JOIN pg_inherits i ON q.oid = i.inhparent
            )
            SELECT oid FROM q WHERE oid = c.oid
        ) as childoid
    FROM
        pg_class c
    INNER JOIN
        pg_namespace n ON n.oid = c.relnamespace
    ORDER BY
        n.nspname, c.relname
), "vAttributeReferences"  as (
    SELECT
        fkr.oid <> fkrd.oid AS "isInheritedSource",
        pkr.oid <> pkrd.oid AS "isInheritedTarget",
        ( fkr.oid <> fkrd.oid OR pkr.oid <> pkrd.oid ) as "isInherited",

        fkn.nspname AS fk_namespace,
        fkr.relname AS fk_relation,
        fkr.oid AS fk_oid,
        fka.attname AS fk_column,
        fka.attnum as "fk_attnum",

        -- initByData
        fkr.oid::text || '.' || fka.attnum::text AS fk_key,

        (
            EXISTS (
                SELECT
                    pg_index.indexrelid,
                    pg_index.indrelid,
                    pg_index.indkey,
                    pg_index.indclass,
                    pg_index.indnatts,
                    pg_index.indisunique,
                    pg_index.indisprimary,
                    pg_index.indisclustered,
                    pg_index.indexprs,
                    pg_index.indpred
                FROM
                    pg_index
                WHERE
                    pg_index.indrelid = fkr.oid AND pg_index.indkey[0] = fka.attnum
            )
        ) AS fk_indexed,
        pkn.nspname AS pk_namespace,
        pkr.relname AS pk_relation,
        pkr.oid AS pk_oid,
        pka.attname AS pk_column,
        pka.attnum as "pk_attnum",

        -- initByData
        pkr.oid::text || '.' || pka.attnum::text AS pk_key,

        (
            EXISTS (
                SELECT
                    pg_index.indexrelid,
                    pg_index.indrelid,
                    pg_index.indkey,
                    pg_index.indclass,
                    pg_index.indnatts,
                    pg_index.indisunique,
                    pg_index.indisprimary,
                    pg_index.indisclustered,
                    pg_index.indexprs,
                    pg_index.indpred
                FROM
                    pg_index
                WHERE
                    pg_index.indrelid = pkr.oid AND
                    pg_index.indkey[0] = pka.attnum
            )
        ) AS pk_indexed,
        c.confupdtype::text || c.confdeltype::text AS ud,
        cn.nspname AS c_namespace,
        c.conname AS c_name
    FROM (
        (
            (
                (
                    (
                        (
                            (
                                pg_constraint c
                                    JOIN
                                pg_namespace cn ON cn.oid = c.connamespace
                            )
                            INNER JOIN
                                "vRelationDescendants" as fkrd ON c.conrelid = fkrd.oid
                            INNER JOIN
                                pg_class fkr ON fkr.oid = fkrd.childoid
                        )
                        JOIN
                            pg_namespace fkn ON fkn.oid = fkr.relnamespace
                    )
                    JOIN
                        pg_attribute fka ON fka.attrelid = c.conrelid AND fka.attnum = ANY (c.conkey)
                )
                INNER JOIN
                    "vRelationDescendants" as pkrd ON c.confrelid = pkrd.oid
                INNER JOIN
                    pg_class pkr ON pkr.oid = pkrd.childoid
            )
            JOIN
                pg_namespace pkn ON pkn.oid = pkr.relnamespace
        )
        JOIN
            pg_attribute pka ON pka.attrelid = c.confrelid AND pka.attnum = ANY (c.confkey)
    )
    WHERE
        c.contype = 'f'::"char" AND
        fkn.nspname = ANY( current_schemas(false) ) AND
        pkn.nspname = ANY( current_schemas(false) )
)
SELECT * FROM "vAttributeReferences" WHERE "isInherited" = false
SQL
        );

        $relationships = $this->db->query($sql);

        foreach( $relationships as $relationship ) {

            $fk = $this->pgAttributes->findOneByKey($relationship['fk_key']);
            $pk = $this->pgAttributes->findOneByKey($relationship['pk_key']);

            if( !$fk or !$pk ) {
                d_pr( $relationship );
                die();
            }

            $fk->addReference($pk);

        }

        // look for normality defined relationships
        $profiler = new Profiler( __FUNCTION__ );
        $profiler->log();

        foreach( $this->pgClasses as $relation ) {

            $tags = $relation->getTags();

            if( isset( $tags['references'] ) ) {

                foreach( $tags['references'] as $reference ) {

                    $reference = array_map( 'trim', explode( '=', $reference) );
                    $reference[1] = explode( '.', $reference[1] );

                    $fk = $relation->getAttributeByName($reference[0]);

                    // referencing
                    $pkTable = $this->pgClasses->findOneByName($reference[1][0]);
                    $pk = $pkTable->getAttributeByName($reference[1][1]);

                    $fk->addReference($pk);

                }

            }

        }

//        echo $profiler->log()->output();

    }

}