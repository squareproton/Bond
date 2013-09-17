/** resolver
{
    "depends": [
        "dev"
    ],
    "searchPath": "dev"
}
*/

DROP VIEW IF EXISTS "vRelationTypeSignature";
CREATE VIEW "vRelationTypeSignature" AS
SELECT
    array_to_string( array_agg( type ), E', ' ) as text,
    md5( array_to_string( array_agg( type ), ',' ) ) as hash,
    oid,
    relname as name,
    nspname as schema
FROM
    (
        SELECT
            ( a.attname || ' ' || t.typname ) AS type,
            c.relname,
            n.nspname,
            c.oid
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
            attnum > 0 AND
            c.relkind IN ( 'r', 'v' )
        ORDER BY
            a.attrelid::text ASC,
            a.attnum ASC
    ) as _
GROUP BY
    oid,
    relname,
    nspname
;