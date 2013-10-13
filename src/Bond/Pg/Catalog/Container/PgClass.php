<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Container;

use Bond\Pg\Catalog\Container;

class PgClass extends Container
{

    protected $class = 'Bond\Pg\Catalog\PgClass';

    protected $setupSql =  <<<SQL
SELECT
    c.oid::int AS oid,
    c.relname AS name,
    n.nspname AS schema,
    c.reltype::int AS "relTypeOid",
    obj_description( c.oid ) AS comment,
    c.relkind as relkind,
    i.inhparent as parent
FROM
    pg_class c
LEFT JOIN
    pg_namespace n ON n.oid = c.relnamespace
LEFT JOIN
    pg_inherits i ON c.oid = i.inhrelid
WHERE
    n.nspname NOT IN ('pg_catalog', 'pg_toast', 'information_schema') AND
    n.nspname = ANY( current_schemas(false) )
    -- AND pg_catalog.pg_table_is_visible( c.oid )
ORDER BY
    n.nspname ASC,
    c.relname ASC
SQL
;

    public function findByName($name)
    {
        $fragments = explode('.', $name);
        switch (count($fragments)) {
            case 1:
                return parent::findByName($fragments[0]);
            case 2:
                return $this->findBySchemaAndName($fragments[0],$fragments[1]);
            default:
                throw new \Exception("`{$name}` isn't something I recognise as a something likely to be in pg_class");
                break;
        }
    }

}
