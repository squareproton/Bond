/** resolver
{
    "depends": [
        "dev"
    ],
    "searchPath": "dev"
}
*/

DROP VIEW IF EXISTS "vMaterialisedViewHelper";
CREATE VIEW "vMaterialisedViewHelper" AS
SELECT
    '( ' || array_to_string( array_agg( cols ), E', ' ) || ' )' as cols,
    '( ' || array_to_string( array_agg( space_cols ), E', ' ) || ' )' as spacecols,
    '( ' || array_to_string( array_agg( v_cols ), E', ' ) || ' )' as vcols,
    '( ' || array_to_string( array_agg( m_cols ), E', ' ) || ' )' as mcols,
    array_to_string( array_agg( definition ), E',\n' ) as definition,
    array_to_string( array_agg( set ), ', ' ) as set,
    array_to_string( array_agg( CASE WHEN is_pk THEN set ELSE NULL END ), ' AND ' ) as set_pk,
    nspname as schema,
    'v' || name_core as vname,
    'm' || name_core as mname,
    oid as oid
FROM
    (
        SELECT
            quote_ident( a.attname ) AS cols,
            ( '  ' || quote_ident( a.attname ) ) AS space_cols,
            ( 'v.' || quote_ident( a.attname ) ) AS v_cols,
            ( 'm.' || quote_ident( a.attname ) ) AS m_cols,
            ( 'm.' || quote_ident( a.attname ) || ' = v.' || quote_ident( a.attname ) ) AS set,
            (
                quote_ident( a.attname ) || ' ' ||
                CASE WHEN
                    tn.nspname IN( 'pg_toast', 'pg_catalog' )
                THEN
                    ''
                ELSE
                    quote_ident( tn.nspname ) || '.'
                END ||
                quote_ident( trim( LEADING '_' FROM t.typname ) ) ||
                CASE WHEN substring( t.typname FOR 1 ) = '_' THEN '[]' ELSE '' END
            ) as definition,
            string_to_array( i.indkey::text, ' ' )::int[] @> ARRAY[ a.attnum::int ] as is_pk,
            substring( c.relname FROM 2 ) as name_core,
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
            pg_index AS i ON c.oid = indrelid AND indisprimary = true
        LEFT JOIN
            pg_namespace n ON n.oid = c.relnamespace
        LEFT JOIN
            pg_namespace tn ON t.typnamespace = tn.oid
        WHERE
            (
--                (n.nspname IN ('materialised') AND c.relkind IN ( 'r' ) ) OR
                (n.nspname IN ('bond') AND c.relkind IN ( 'v' ) )
            ) AND
            attisdropped = false AND
            attnum > 0
        ORDER BY
            a.attrelid::text ASC,
            a.attnum ASC
    ) as _
GROUP BY
    oid,
    relname,
    name_core,
    schema
;

DROP VIEW IF EXISTS "vMaterialisedViewUpdateSql";
CREATE VIEW "vMaterialisedViewUpdateSql" AS
SELECT
    mname,
    E'UPDATE\n    ' ||
    'materialised.' || quote_ident( mname ) ||
    E' AS m\nSET\n    ' ||
    spacecols || E'\n  = ' || vcols ||
    E'\nFROM\n    ' ||
    quote_ident( schema ) || '.' || quote_ident( vname ) ||
    E' AS v\nWHERE\n    ROW(m.*) IS DISTINCT FROM ROW(v.*) AND\n    ' ||
    set_pk ||
    E'\n;' AS update
FROM
    "vMaterialisedViewHelper"
;