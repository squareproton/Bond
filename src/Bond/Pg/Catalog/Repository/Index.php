<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Repository;

use Bond\Container;
use Bond\Repository\Multiton;

use Bond\Sql\Query;
use Bond\Pg\Result;

/**
 * Description of Repository
 * @author pete
 */
class Index extends Multiton
{

    /**
     * Garbage collection
     * @var int|null
     */
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
        );

        $datas = $this->db->query( $query )->fetch( Result::TYPE_DETECT );
        parent::cacheInvalidate();
        $this->initByDatas( $datas );

    }

    /**
     * Return a index from it's name
     * @param string
     * @return Entity\Relation
     */
    public function findByName( $name )
    {

        // is this name schema qualified
        $call = sprintf(
            "findBy%s",
            ( false !== strpos( $name, '.' ) ) ? 'FullyQualifiedName' : 'Name'
        );

        $found = $this->persistedGet()->$call( $name );

        // return
        switch( $count = count( $found ) ) {
            case 0:
                return null;
            case 1:
                return $found->pop();
            default:
                throw new \LogicException( "{$count} relations' found with name `{$name}`. Ambiguous. Can't proceed." );
        }

    }

    public function findByIndrelid( $indrelid )
    {

        return $this->persistedGet( $indrelid )->findByIndrelid( (int) $indrelid );

    }

    /**
     * Data from pg_index
     * @param string|int $oid
     * @return array
     */
    public function data( $oid )
    {
        throw new \Exception("Depreciated");
    }

}