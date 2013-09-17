/** resolver
{
    "depends": [
        "dev"
    ],
    "searchPath": "dev"
}
*/

DROP VIEW IF EXISTS "vStatViews";

-- show statistics on views in the database
CREATE VIEW "vStatViews" AS
SELECT 
    idstat.schemaname AS schema_name,
    idstat.relname AS table_name,
    indexrelname AS index_name,
    idstat.idx_scan AS times_used,
    pg_size_pretty( pg_relation_size( idstat.relid ) ) AS table_size,
    pg_size_pretty( pg_relation_size( indexrelid ) ) AS index_size,
    n_tup_upd + n_tup_ins + n_tup_del as num_writes,
    indexdef AS definition
FROM 
    pg_stat_user_indexes AS idstat 
INNER JOIN 
    pg_indexes ON (indexrelname = indexname AND idstat.schemaname = pg_indexes.schemaname)
INNER JOIN
    pg_stat_user_tables AS tabstat ON idstat.relid = tabstat.relid
-- WHERE 
--    idstat.idx_scan < 1000
;