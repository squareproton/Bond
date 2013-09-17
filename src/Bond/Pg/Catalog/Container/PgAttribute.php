<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Container;

use Bond\Pg\Catalog\Container;

class PgAttribute extends Container
{

    protected $class = 'Bond\Pg\Catalog\PgAttribute';

    protected $setupSql =  <<<SQL
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
    i.character_maximum_length as "length",
    description.description as "comment"
FROM
    pg_attribute AS a
INNER JOIN
    pg_type AS t ON t.oid = a.atttypid
INNER JOIN
    pg_class AS c ON c.oid = a.attrelid
LEFT JOIN
    pg_catalog.pg_namespace n ON n.oid = c.relnamespace
-- some pgType objects don't report length as you might expect so we need to check information schema
LEFT JOIN
    information_schema.columns as i ON n.nspname = i.table_schema AND c.relname = i.table_name AND i.column_name = a.attname
LEFT JOIN
    pg_attrdef AS d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
LEFT JOIN
    pg_description AS description ON description.objoid = a.attrelid AND description.objsubid = a.attnum
WHERE
    n.nspname NOT IN ('pg_catalog', 'pg_toast', 'information_schema') AND
    n.nspname = ANY( current_schemas(false) ) AND
    attisdropped = false AND
    attnum > 0
ORDER BY
    a.attrelid::text ASC,
    a.attnum ASC
SQL
;

    public function findByIdentifier( $identifier )
    {
        $identifier = explode(".", $identifier);
        return $this->catalog->pgClasses->findOneByName($identifier[0])->getAttributeByName($identifier[1]);
    }

}
