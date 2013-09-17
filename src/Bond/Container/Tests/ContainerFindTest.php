<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\Tests;

use Bond\Container;

class EntityContainerFindTest extends \PHPUnit_Framework_Testcase
{

    public function makeContainer( $n = 100 )
    {
        $setArray = array();
        $c = 0;
        while( $c < $n ) {
            $setArray[] = new EBTC( $c, "name-{$c}" );
            $c++;
        }
        return new Container( $setArray );
    }

    public function testFindBy()
    {

        $container = $this->makeContainer();
        $set = array_values( $container->collection );

        $found = $container->findById( 20 );
        $this->assertTrue( $found->isSameAs( new Container( $set[20] ) ) );

        // strict type casting
        $found = $container->findById( "20" );
        $this->assertTrue( $found->isEmpty() );

        $found = $container->findByName( "name-20" );
        $this->assertTrue( $found->isSameAs( new Container( $set[20] ) ) );

        $found = $container->findByIdOrName( 15, "name-20" );
        $this->assertTrue( $found->isSameAs( new Container( $set[15], $set[20] ) ) );

        $found = $container->findByIdAndName( 15, "name-20" );
        $this->assertTrue( $found->isEmpty() );

    }

    public function testFindByArray()
    {
        $container = $this->makeContainer();
        $set = array_values( $container->collection );

        $found = $container->findById( range(0,2) );
        $this->assertTrue( $found->isSameAs( new Container( $set[0], $set[1], $set[2] ) ) );
    }

    public function testFindByContainer()
    {
        // make a container that contains objects for id
        $c = 0;
        while( $c++ < 10 ) {
            $o = new \stdclass();
            $o->c = $c;
            $objs[$c] = $o;
            $set[] = new EBTC( $o, "name-{$c}" );
        }

        $container = new Container($set);
        $oneTwoThree = new Container( $objs[1], $objs[2], $objs[3] );

        $found = $container->findById($oneTwoThree)->setPropertyMapper();
        $this->assertSame( $found->count(), 3 );

        $this->assertSame(
            $found->pluck('id')->setPropertyMapper()->pluck('c'),
            array(1,2,3)
        );

    }

    public function testFindOneBy()
    {

        $container = $this->makeContainer();
        $set = array_values( $container->collection );

        $found = $container->findOneById( 20 );
        $this->assertSame( $found, $set[20] );

        // strict type casting
        $found = $container->findOneById( "20" );
        $this->assertNull( $found );

        $this->setExpectedException("Bond\Container\Exception\IncompatibleQtyException");
        $found = $container->findOneByIdOrName( 1, "name-20" );

    }

}