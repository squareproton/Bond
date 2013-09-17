<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Container;

use Bond\Pg\Catalog\Container;

class PgType extends Container
{

    protected $class = 'Bond\Pg\Catalog\PgType';

    protected $setupSql =  <<<SQL
SELECT
    t.oid AS oid,
    t.typname AS name,
    n.nspname as "schema",
    case
        when t.typlen = -1 THEN null::smallint
        ELSE t.typlen::smallint
    END as length,
    t.typtype AS type,
    t.typcategory AS category,
    t.typarray AS "arrayType",
    t.typdefault AS default,
    t.typdefaultbin AS "defaultBin",
    CASE
        WHEN t.typcategory = 'E' THEN ( SELECT array_agg(enumlabel)::text[] FROM pg_enum WHERE enumtypid = t.oid )
        ELSE null::text[]
    END as "enumOptions"
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
SQL
;

}