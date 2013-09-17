/** resolver
{
    "depends": [
        "dev"
    ],
    "searchPath": "dev"
}
*/

-- get a list of relationDescendants
-- defaults to including self
CREATE OR REPLACE FUNCTION relationDescendants( oid, bool DEFAULT TRUE )
RETURNS SETOF oid AS $$

    WITH RECURSIVE
    q( oid ) AS
    (
        SELECT 
            c.oid
        FROM 
            pg_class c
        WHERE
            c.oid = $1

        UNION ALL

        SELECT i.inhrelid FROM q INNER JOIN pg_inherits i ON q.oid = i.inhparent
    )
    SELECT oid FROM q WHERE $2 OR oid = $1;
    
$$ LANGUAGE sql VOLATILE;

-- return a set of this relation's parents
-- defaults to including self
CREATE OR REPLACE FUNCTION relationAncestors( oid, bool DEFAULT TRUE )
RETURNS SETOF oid AS $$

    WITH RECURSIVE
    q( oid ) AS
    (
        SELECT 
            c.oid
        FROM 
            pg_class c
        WHERE
            c.oid = $1

        UNION ALL

        SELECT i.inhparent FROM q INNER JOIN pg_inherits i ON q.oid = i.inhrelid
    )
    SELECT oid FROM q WHERE $2 OR oid = $1;
    
$$ LANGUAGE sql VOLATILE;

CREATE OR REPLACE VIEW "vRelationDescendants" AS
SELECT 
    c.oid,
    c.relname,
    relationDescendants( c.oid ) as childoid
FROM 
    pg_class c 
INNER JOIN
    pg_namespace n ON n.oid = c.relnamespace
ORDER BY 
    n.nspname, c.relname
;

-- Based on. http://code.google.com/p/pgutils/ but very heavily modified
-- Potentially inheritance aware/compensating. Be careful. Use the unit tests.

DROP VIEW IF EXISTS "vAttributeReferences";
CREATE VIEW "vAttributeReferences" AS
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
                dev."vRelationDescendants" as pkrd ON c.confrelid = pkrd.oid
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
;