<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver\Tests;

use Bond\DependencyResolver;
use Bond\DependencyResolver\ResolverList;

class ListTest extends \PHPUnit_Framework_Testcase
{

    private function makeResolver($id)
    {
        return new DependencyResolver(
            $id,
            function() use ($id) {
                echo "resolving `{$id}`\n";
            }
        );
    }

    public function testInstantate()
    {
        $resolvers = [ $this->makeResolver(0), $this->makeResolver(1) ];
        $list = new ResolverList( $resolvers );

        $this->assertSame( 2, count($list) );

        $c = 0;
        foreach( $list as $resolver ) {
            $this->assertSame( $resolver, $resolvers[$c++] );
        }
    }

    public function testDuplicateAddition()
    {
        $a = $this->makeResolver('a');
        $list = new ResolverList([$a]);

        $this->assertSame( count($list), 1 );
        $list[] = $a;
        $this->assertSame( count($list), 1 );
    }

    public function testContains()
    {
        $list = new ResolverList();
        $a = $this->makeResolver('a');

        $this->assertFalse( $list->contains($a) );
        $this->assertFalse( $list->containsId('a') );

        $list[] = $a;
        $this->assertTrue( $list->contains($a) );
        $this->assertTrue( $list->containsId('a') );
    }

    public function testRemove()
    {
        $a = $this->makeResolver('a');
        $list = new ResolverList([$a]);

        $this->assertSame( count($list), 1 );
        $this->assertSame( $list, $list->remove($a) );
        $this->assertSame( count($list), 0 );

        $list[] = $a;
        $this->assertSame( $list, $list->removeById('a') );
        $this->assertSame( count($list), 0 );
    }

    public function testGetById()
    {
        $a = $this->makeResolver('a');
        $list = new ResolverList([$a]);

        $this->assertSame( $a, $list->getById('a') );
        $this->assertNull( $list->getById('not in list') );
    }

}