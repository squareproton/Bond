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

class ResolverTest extends \PHPUnit_Framework_Testcase
{

    private function makeResolver($id, array &$resolution = array() )
    {
        // $id = (string) $id;
        return new DependencyResolver(
            $id,
            function() use ($id, &$resolution) {
                $resolution[] = $id;
            }
        );
    }

    private function makeResolverSet( $n, array &$resolution = array() )
    {
        $list = [];
        foreach( range(1,$n) as $c ) {
            $list[] = $currentResolver = $this->makeResolver($c, $resolution);
            if( isset( $lastResolver ) ) {
                $lastResolver->addDependency($currentResolver);
            }
            $lastResolver = $currentResolver;
        }
        return $list;

    }

    public function testAddDependency()
    {
        $a = $this->makeResolver('a');
        $b = $this->makeResolver('b');
        $a->addDependency( $b );

        $a->resolve(new ResolverList(), new ResolverList());
    }

    public function testInstantate()
    {
        $list = $this->makeResolverSet(50);
        $list[0]->resolve(new ResolverList(), new ResolverList());
    }

    public function testCircularDependency()
    {
        $a = $this->makeResolver('a');
        $b = $this->makeResolver('b');
        $a->addDependency($b);
        $b->addDependency($a);

        $this->setExpectedException("Bond\\DependencyResolver\\Exception\\CircularDependencyException");
        $a->resolve( new ResolverList(), new ResolverList() );
    }

    public function testResolution()
    {
        $resolution = array();
        $list = $this->makeResolverSet(10, $resolution );

        $this->assertSame( $resolution, array() );
        $list[0]->resolve( new ResolverList(), new ResolverList() );

        // check the resolution works backwards
        $resolution = array_reverse( $resolution );
        $this->assertSame( range(1,10), $resolution );

    }

}