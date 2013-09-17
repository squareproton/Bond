<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver\Tests;

use Bond\DependencyResolver;
use Bond\DependencyResolver\Sql;
use Bond\DependencyResolver\ResolverList;

use Bond\Pg\Tests\PgProvider;

use Bond\Entity\Types\Json;

class ResolverSqlTest extends PgProvider
{

    public function testMakeResolver()
    {
        $db = $this->connectionFactory->get('RW');
        $resolver = new Sql(
            'id',
            $db,
            'SELECT 1;'
        );
    }

    public function testDependencies()
    {
        $db = $this->connectionFactory->get('RW');
        $resolver = new Sql(
            'id',
            $db,
            <<<SQL
/** resolver
{
    "depends": ["spanner","monkey"]
}
*/
SELECT 1;
SQL
        );

        $depends = $resolver->getSqlDepends();
        $this->assertSame( $depends, ["spanner","monkey"] );

        $list = new ResolverList(
            [
                new DependencyResolver('spanner',function(){}),
                new DependencyResolver('monkey', function(){})
            ]
        );

        // set the dependencies from sql
        $resolver->setSqlDepends( $list );
        $this->assertSame( count($resolver->getDepends()), 2 );

    }

    public function testModifyResolverBlock()
    {

        $sql = <<<SQL
/** resolver
{ "depends": [] }
*/
SELECT 1
SQL;

        $db = $this->connectionFactory->get('RW');
        $resolver = new Sql( 'id', $db, $sql );
        $resolver->modifyResolverBlock(
            function ( Json $input ) {
                return $input;
            }
        );

    }

    public function testResolverBlockRegex()
    {

        $sql = <<<SQL
/** resolver
{
    "depends": [
        "functions",
        "temporal"
    ]
}
*/

/** check the regex is ungreedy */
*/
SQL
;

        $db = $this->connectionFactory->get('RW');
        $resolver = new Sql( 'id', $db, $sql );

        $depends = $resolver->getSqlDepends();
        $this->assertSame( $depends, ["functions","temporal"] );

    }

}