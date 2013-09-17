<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Container;

use Bond\Pg\Catalog\Container;

class PgIndex extends Container
{

    protected $class = 'Bond\Pg\Catalog\PgIndex';

    protected $setupSql =  <<<SQL
SELECT
    n.nspname "schema",
    c.relname "name",
    string_to_array(
        indrelid::text || '.' || replace( indkey::text, ' ', ' ' || indrelid::text || '.' ),
        ' '
    ) AS columns,
    false "inherited",
    i.*,
    string_to_array( indkey::text, ' ' )::int[] as indkey
FROM
    pg_index i
INNER JOIN
    pg_class c ON i.indexrelid = c.oid
LEFT JOIN
    pg_namespace n ON n.oid = c.relnamespace
WHERE
    n.nspname NOT IN ('pg_catalog', 'pg_toast', 'information_schema')
SQL
;

}
