<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\Tests;

use Bond\Container\ContainerObjectAccess as Container;
use Bond\Container\ContainerableInterface;

function generateSortByPropertyClosure( $property, $direction = SORT_ASC )
{

    if( $direction === SORT_DESC ) {
        $aLTb = 1;
        $aGTb = -1;
    } else {
        $aLTb = -1;
        $aGTb = 1;
    }

    return function ( $a, $b ) use ( $property, $aLTb, $aGTb ) {
        $propertyA = $a->$property;
        $propertyB = $b->$property;
        if( $propertyA === $propertyB ) {
            return 0;
        }
        return ( $propertyA < $propertyB ) ? $aLTb : $aGTb;
    };

}

class EntityContainerTest extends \PHPUnit_Framework_Testcase
{

    public function makeSet( $n = 10 )
    {
        $setArray = array();
        $c = 0;
        while( $c < $n ) {
            $setArray[] = new EBTC( $c, "name-{$c}" );
            $c++;
        }
        return $setArray;
    }

    public function dataProvider()
    {
        return array(
            array(
                new Container(),
                $this->makeSet(10),
            ),
        );
    }

    /**
     * @dataProvider dataProvider
     */
    public function testContainerCopy( $container, $set )
    {
        $container->add( $set );
        $copy = $container->copy();

        $this->assertTrue( $container->isSameAs( $copy ) );
        $this->assertFalse( $container === $copy );

        // deep copy
        $deepCopy = $container->copy( true );
        $this->assertFalse( $container->isSameAs($deepCopy) );
        $this->assertSame( $container->count(), $deepCopy->count() );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainerAddArraySet( $container, $set )
    {
        $this->assertSame( count($container), 0 );

        $container->add( $set );
        $this->assertSame( count($container), 10 );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainerAddArraySets( $container, $set )
    {
        $this->assertSame( count($container), 0 );

        $container->add( $set );
        $this->assertSame( count($container), 10 );

        $container->add( $this->makeSet() );
        $this->assertSame( count( $container ), 20 );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainerIsIterator( $container, $set )
    {
        $reflection = new \ReflectionClass($container);
        $this->assertTrue( $reflection->isIterateable() );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainerIteration( $container, $set )
    {

        $container->add( $set );

        $key = 0;
        foreach( $container as $value ) {

            if( $value !== $set[$key] ){
                $this->fail('Iteration Test Fail');
            }
            $key++;
        }

    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainsArraySet( $container, $set )
    {
        $this->assertFalse( $container->contains( $set ) );
        $container->add( $set );

        $this->assertTrue( $container->contains( $set ) );
        $this->assertTrue( $container->contains( $set, $set ) );
        $this->assertFalse( $container->contains( $this->makeSet() ) );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainsContainer( $container, $set )
    {
        $this->assertFalse( $container->contains( $set ) );

        $container->add( $set );
        $this->assertTrue( $container->contains( $container ) );
        $this->assertTrue( $container->contains( $container ) );
    }
  //
    public function testContainsWithMultipleArguments()
    {
        $set1 = $this->makeSet(10);
        $set2 = $this->makeSet(10);

        $container = new Container( $set1, $set2 );

        $this->assertEquals( count($container), 20 );
        $this->assertTrue( $container->contains( $set1, $set2 ) );

        $container->remove( $set2 );
        $this->assertFalse( $container->contains( $set1, $set2 ) );
      //
    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainsSingleEntityBase( $container )
    {
        $entity = new EBTC();
        $this->assertFalse( $container->contains( $entity ) );

        $container->add( $entity );
        $this->assertTrue( $container->contains( $entity ) );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainsNullOrNullLikeThing( $container )
    {
        $this->assertTrue( $container->contains( null ) );
        $this->assertTrue( $container->contains( array() ) );
        $this->assertTrue( $container->contains( array( null ) ) );
        $this->assertTrue( $container->contains( new Container() ) );

        $this->assertTrue( $container->contains( new Container(), null, array() ) );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainsStdClass( $container )
    {
        $this->setExpectedException('Bond\Exception\BadTypeException');
        $container->contains( "not a class" );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testSearchArray( $container, $set )
    {

        $container->add( $set );

        $searchResult = $container->search( $set );
        foreach( $searchResult as $keySet => $keyContainer ) {
            if( $container[$keyContainer] !== $set[$keySet] ) {
                $this->fail("fuck");
            }
        }

    }

    /**
    * @dataProvider dataProvider
    */
    public function testSearchArraySubset( $container, $set )
    {

        $container->add( $set );

        $searchResult = $container->search( array( $set[1], $set[2] ) );

        $searchResult = $container->search( $set );
        foreach( $searchResult as $keySet => $keyContainer ) {
            if( $container[$keyContainer] !== $set[$keySet] ) {
                $this->fail("fuck");
            }
        }

    }

    /**
    * @dataProvider dataProvider
    */
    public function testSearchArrayShuffle( $container, $set )
    {

        $container->add( $set );

        shuffle( $set );

        $searchResult = $container->search( $set );

        $searchResult = $container->search( $set );
        foreach( $searchResult as $keySet => $keyContainer ) {
            if( $container[$keyContainer] !== $set[$keySet] ) {
                $this->fail("fuck");
            }
        }

        // subset
        $set = array_values( $set );

        $set = array_slice( $set, 0, 5 );

        $search = $container->search( $set );

        $this->assertSame( $container[$search[0]], $set[0] );
        $this->assertSame( $container[$search[1]], $set[1] );

    }

    /**
    * @dataProvider dataProvider
    */
    public function testSearchContainer( $container, $set )
    {

        $container->add( $set );

        $searchResult = $container->search( $container );
        $this->assertSame( array_values($searchResult), array_keys($container->collection) );
        $this->assertSame( $container->search( array() ), array() );
        $this->assertSame( $container->search( array( null ) ), array( false ) );
        $this->assertSame( $container->search( new Container() ), array() );

    }

    /**
    * @dataProvider dataProvider
    */
    public function testAddSingly( $container, $set )
    {
        foreach( $set as $value ) {
            $container->add( $value );
        }

        $this->assertTrue( $container->contains( $set ) );
        $this->assertSame( count($container), count($set) );

        // Repeating the loop shouldn't duplicate the entities.
        foreach( $set as $value ) {
            $container->add( $value );
        }

        $this->assertSame( count( $container ), count( $set ) );
        $this->assertTrue( $container->contains( $set ) );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testAddVariableArguments( $container, $set )
    {
        $container->add( null, null );
        $this->assertSame( count( $container ), 0 );

        $container->add( new Container( $this->makeSet() ), $set, new EBTC(), null );
        $this->assertSame( count( $container ), 21 );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testAddArray( $container, $set )
    {
        $container->add( $set );

        $this->assertTrue( $container->contains( $set ) );
        $this->assertSame( count($container), count($set) );

        $container->add( $set );
        $this->assertSame( count($container), count($set) );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testAddCollection( $container, $set )
    {
        $container->add( new Container( $set ) );

        $this->assertTrue( $container->contains( $set ) );
        $this->assertSame( count( $container ), count( $set ) );

        // repeated addition does nothing
        $container->add( new Container( $set ) );
        $this->assertSame( count( $container ), count( $set ) );

        $container->add( new Container( $this->makeSet() ) );
        $this->assertSame( count( $container ), 20 );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testRemoveArraySet( $container, $set )
    {
        $this->assertSame( count( $container ), 0 );

        $container->add( $set );
        $this->assertSame( count( $container ), 10 );

        $container->remove( $set );
        $this->assertSame( count( $container ), 0 );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testRemoveArraySets( $container, $set )
    {
        $this->assertSame( count( $container ), 0 );

        $container->add( $set );
        $this->assertSame( count( $container ), 10 );

        $set2 = $this->makeSet();

        $container->add( $set2 );
        $this->assertSame( count( $container ), 20 );

        $this->assertSame( $container->remove( $set2 ), 10 );
        $this->assertSame( count( $container ), 10 );

        $this->assertSame( $container->remove( $set[0] ), 1 );
        $this->assertSame( count( $container ), 9 );

        $this->assertSame( $container->remove( $set ), 9 );
        $this->assertSame( count( $container ), 0 );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testRemove2( $container, $set )
    {
        $container->add( $set );

        $this->assertSame( $container->remove( $set[1] ), 1 );
        $this->assertSame( count( $container ), count( $set ) -1 );

        $this->assertSame( $container->remove( new Container( array( $set[4] ) ) ) , 1 );
        $this->assertSame( count( $container ), count( $set ) -2 );

        $this->assertSame( $container->remove( new Container( $set ) ), 8 );
        $this->assertSame( count( $container ), 0 );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testRemoveMultipleArguments( $container, $set )
    {
        $container->add( $set );
        $this->assertSame( $container->remove( $set[1], $set[2] ), 2 );
        $this->assertSame( count( $container ), count( $set ) -2 );
    }

    /**
    * @dataProvider dataProvider
    */
    public function testRemoveBy( $container, $set )
    {
      //
        $container->add( $set );

        $this->assertSame( $container->removeById( 1 ), $container );
        $this->assertSame( count( $container ), count( $set ) -1 );
        $this->assertFalse( $container->contains( $set[1] ) );

        $container->removeByIdAndName( 2, 'name-2' );
        $this->assertFalse( $container->contains( $set[2] ) );

        $container->removeByIdAndName( 4, 'name-99' );
        $this->assertTrue( $container->contains( $set[4] ) );

    }

    /**
    * @dataProvider dataProvider
    */
    public function testObjectsMustBeSameClass( $container, $set )
    {

        $container->add( $set );
        $container->add( null, array(), new Container() );

        $container->add( array( new EBTC1() ) ); // EBTC1 extends ETBC

    }

    public function testContainersMustBeSameClass()
    {

        $c1 = new Container();
        $c1->classSet('spanner');

        $c2 = new Container();
        $c2->classSet('monkey');

        $this->setExpectedException("\\Bond\\Container\\Exception\\IncompatibleContainerException");
        $c1->add( $c2 );

    }

    public function testContainerInheritance()
    {

        $c1 = new Container( new EBTC() );
        $c2 = new Container( new EBTC1() );

        $c3 = new Container( $c1, $c2 );
        $this->assertSame( $c3->count(), 2 );

    }

    /**
    * @dataProvider dataProvider
    */
    public function testContainerIterationForeach( $container, $set )
    {
        $container->add( $set );
        foreach( $container as $child ) {
            $this->assertSame( $child, array_shift($set) );
        }
    }

    public function testTruncate()
    {
        $c2 = new Container( new EBTC1() );
        $this->assertSame( $c2->truncate()->count(), 0 );
    }

    public function testClassSet()
    {

        $c2 = new Container();
        $this->assertTrue( $c2->classSet('monkey') );
        $this->assertSame( $c2->class, 'monkey' );
        $this->assertFalse( $c2->classSet('monkey') );

        $this->setExpectedException("LogicException");
        $c2->classSet('rabbit');

    }

    public function testClassSetFromNewContainer()
    {

        $c1 = new Container();
        $c1->classSet('spanner');

        $c2 = new Container( $c1 );
        $this->assertSame( $c2->class, 'spanner' );

    }

    public function testClassSetFromObject()
    {

        $c1 = new Container();
        $c1->classSet('spanner');

        $c2 = new Container();
        $c2->classSet( $c1 );
        $this->assertSame( $c1->class, $c2->class );

        $entity = new EBTC();
        $c4 = new Container();
        $c4->classSet( $entity );
        $this->assertSame( $c4->class, get_class( $entity ) );

    }

    public function testClassGet()
    {

        $container = new Container();
        $this->assertNull( $container->class );

        $obj1 = new EBTC();
        $container->add( $obj1 );
        $this->assertSame( get_class($obj1), $container->class );

        $container->remove( $obj1 );
        $this->assertSame( get_clasS($obj1), $container->class );

    }

    public function testSplitByClass()
    {
        $one = new Container( new EBTC(), new EBTC() );
        $two = new Container( new EBTC1(), new EBTC1() );

        $combined = new Container( $one, $two );
        $output = $combined->splitByClass();

        $this->assertTrue( $output['Bond\Container\Tests\EBTC']->isSameAs( $one ) );
        $this->assertTrue( $output['Bond\Container\Tests\EBTC1']->isSameAs( $two ) );

    }

    public function testIsEmpty()
    {
        $container = new Container();
        $this->assertTrue( $container->isEmpty() );

        $container->add( $this->makeSet(10) );
        $this->assertFalse( $container->isEmpty() );

    }

    public function testIsSameAs()
    {

        $set = $this->makeSet(10);
        $c1 = new Container( $set );
        shuffle( $set );
        $c2 = new Container( $set );
        $this->assertTrue( $c1->isSameAs( $c2 ) );

        $c2->remove( $set[0] );
        $this->assertFalse( $c1->isSameAs( $c2 ) );

        $c1->remove( $set[0] );
        $this->assertTrue( $c1->isSameAs( $c2 ) );
        $this->assertTrue( $c2->isSameAs( $c1 ) );
      //
    }

    public function testIsSameAsOrderingIsSignificant()
    {

        $set = $this->makeSet(10);
        $c1 = new Container( $set );
        shuffle( $set );
        $c2 = new Container( $set );

        $this->assertFalse( $c1->isSameAs( $c2, true ) );
        $this->assertTrue( $c1->isSameAs( $c2, false ) );

    }

    public function testContainsEmptyContainersOfDifferentTypes()
    {

        $c1 = new Container();
        $c2 = new Container();

        $this->assertTrue( $c1->isSameAs( $c2 ) );

        $c2->classSet('monkey');
        $this->assertTrue( $c1->isSameAs( $c2 ) );

        $c1->classSet('fish');
        $this->assertFalse( $c1->isSameAs( $c2 ) );

    }

    public function testFilter()
    {

        $c1 = new Container( $this->makeSet(10) );

        $c1->filter(
            function($entity){
                return $entity->id < 5;
            }
        );
        $this->assertSame( count($c1), 5 );

    }

    // add duplicates
    public function testAddDuplicates()
    {

        $obj1 = new EBTC();

        $c1 = new Container( array( $obj1, $obj1 ), $obj1, null );

        $this->assertSame( count( $c1 ), 1 );

    }

    public function testPopAndShift()
    {

        $set = $this->makeSet(5);

        $c = new Container($set);

        $this->assertSame( $c->pop(), array_pop( $set ) );
        $this->assertSame( $c->shift(), array_shift( $set ) );
        $this->assertSame( $c->pop(), array_pop( $set ) );
        $this->assertSame( $c->shift(), array_shift( $set ) );
        $this->assertSame( $c->pop(), array_pop( $set ) );
        $this->assertSame( $c->shift(), array_shift( $set ) );
        $this->assertSame( $c->pop(), array_pop( $set ) );

        $this->assertSame( count( $c ), 0 );

    }

    public function testEach()
    {

        $container = new Container( $this->makeSet(5) );

        $container->each(
            function($entity){
                $entity->name = 'name';
            }
        );

        foreach( $container as $entity ) {
            if( $entity->name !== 'name' ) {
                $this->fail("nope");
            }
        }

    }

    public function testMap()
    {

        $n = 2;
        $container = new Container( $this->makeSet($n) );

        $output = $container->map(
            function($entity){
                return $entity->name;
            }
        );

        $c = 0;
        foreach( $output as $name ) {
            $this->assertSame( $name, "name-{$c}" );
            $c++;
        }

    }

    public function testMapCombine()
    {

        $n = 2;
        $container = new Container( $this->makeSet($n) );

        $output = $container->mapCombine(
            function($e){ return $e->id; },
            function($e){ return $e->name; }
        );

        $c = 0;
        foreach( $output as $key => $name ) {
            $this->assertSame( $key, $c );
            $this->assertSame( $name, "name-{$c}" );
            $c++;
        }
      //
    }

    public function testContainerIntersect()
    {

        $set = $this->makeSet( 10 );

        $c_all = new Container( $set );

        $c_even = new Container( $set );
        $c_even->filter(function($e){ return !($e->id%2); });

        $c_odd = new Container( $set );
        $c_odd->filter(function($e){ return $e->id%2; });

        $c_three = new Container( $set );
        $c_three->filter(function($e){ return !($e->id%3); });

        $this->assertSame( 10, count( $c_all->intersect( $c_all ) ) );
        $this->assertSame( 5, count( $c_all->intersect( $c_odd ) ) );
        $this->assertSame( 5, count( $c_all->intersect( $c_even ) ) );
        $this->assertSame( 0, count( $c_all->intersect( $c_odd, $c_even ) ) );
        $this->assertSame( 2, count( $c_all->intersect( $c_three, $c_even ) ) );

        $this->assertSame( 0, count( $c_all->intersect( new Container() ) ) );

    }

    public function testContainerIntersectThrowExceptionsWhenPassedContainersOfADifferentClass()
    {

        $c_spanner = new Container();
        $c_spanner->classSet('spanner');

        $c_goat = new Container();
        $c_goat->classSet('goat');

        $this->setExpectedException("Bond\\Container\\Exception\\IncompatibleContainerException");
        $c_spanner->intersect( $c_goat );
     //
    }

    public function testContainerIntersectExceptionWithBadArguments()
    {
        $c = new Container();
        $this->setExpectedException("InvalidArgumentException");
        $c->intersect( "not a object" );
    }

    public function testContainerDiff()
    {

        $set = $this->makeSet( 10 );

        $c_all = new Container( $set );

        $c_even = new Container( $set );
        $c_even->filter(function($e){ return !($e->id%2); });

        $c_odd = new Container( $set );
        $c_odd->filter(function($e){ return $e->id%2; });

        $c_three = new Container( $set );
        $c_three->filter(function($e){ return !($e->id%3); });

        // all diff empty
        $this->assertTrue(
            $c_all->isSameAs(
                $c_all->diff( new Container() )
            )
        );

        // empty diff empty
        $c_empty = new Container();
        $this->assertTrue(
            $c_empty->isSameAs(
                $c_empty->diff( new Container() )
            )
        );

        // all diff even + odd
        $this->assertTrue(
            $c_empty->isSameAs(
                $c_all->diff( $c_even, $c_odd )
            )
        );

        // three diff even
        $this->assertSame(
            2,
            count( $c_three->diff( $c_even ) )
        );

    }

    public function testSort()
    {

        $id = new Container(
            $a = new EBTC(1, 'c'),
            $b = new EBTC(2, 'b'),
            $c = new EBTC(3, 'a')
        );
        $name = new Container( $c, $b, $a );
        $working = new Container( $a, $c, $b );

        $working->sort( generateSortByPropertyClosure('name') );
        $this->assertTrue( $working->isSameAs( $name, true ) );

        $working->sort( generateSortByPropertyClosure('id') );
        $this->assertTrue( $working->isSameAs( $id, true ) );

        $working->sort( generateSortByPropertyClosure('id', SORT_DESC) );
        $this->assertTrue( $working->isSameAs( $name, true ) );

        $working->sort( generateSortByPropertyClosure('name', SORT_DESC) );
        $this->assertTrue( $working->isSameAs( $id, true ) );

        // sort by property name
        $this->assertTrue( $working->sortByName()->isSameAs( $name, true ) );
        $this->assertTrue( $working->sortById()->isSameAs( $id, true ) );
        $this->assertTrue( $working->sortById( SORT_DESC )->isSameAs( $name, true ) );
        $this->assertTrue( $working->sortByName( SORT_DESC )->isSameAs( $id, true ) );

    }

    public function testContainerWhileList()
    {

        $entities = array(
            new EBTC(array('id'=>1)),
            new EBTC(array('id'=>2)),
            new EBTC(array('id'=>3))
        );

        $container = new Container( $entities );

        $c = 0;
        while( $entity = $container->current() ) {
            $container->next();
            $this->assertSame( $entity, $entities[$c] );
            $c++;
        }

    }

    public function testContainerRandomGet()
    {
        $container = new Container();
        foreach( range(1,100) as $n ) {
            $container->add( new EBTC($n) );
        }

        $this->assertTrue( $container->randomGet() instanceof ContainerableInterface );
        $this->assertTrue( $container->randomGet(1) instanceof ContainerableInterface );
        $this->assertTrue( $container->randomGet(1, true) instanceof Container );
        $this->assertTrue( $container->randomGet(0) instanceof Container );
        $this->assertSame( $container->randomGet(0)->count(), 0 );
        $this->assertSame( $container->randomGet(5)->count(), 5 );

        $container = new Container();
        $this->assertNull( $container->randomGet() );
        $this->assertTrue( $container->randomGet(6)->isEmpty() );

        // asking for more than is avaliable just returns you everything
        $container->add( new EBTC() );
        $this->assertSame( $container->randomGet(6)->count(), 1 );

    }

    public function testContainerRandomGetRemoveElements()
    {
        $container = new Container();
        foreach( range(1,5) as $n ) {
            $container->add( new EBTC(array('id'=> $n)) );
        }

        $container->randomGet(2, null, true );
        $this->assertSame( $container->count(), 3 );

        $container->randomGet(null, null, true );
        $this->assertSame( $container->count(), 2 );

        $container->randomGet(8, null, true );
        $this->assertSame( $container->count(), 0 );

    }

    public function testContainerAnyEvery()
    {
        $container = new Container(
            $a = new EBTC(1, 'a'),
            $b = new EBTC(2, 'b'),
            $c = new EBTC(3, 'c')
        );

        $isEven = function( $entity ) {
            return $entity->id % 2 === 0;
        };
        $nameC = function( $entity ) {
            return $entity->name === 'c';
        };
        $alwaysTrue = function( $entity ) {
            return true;
        };

        $this->assertTrue( $container->any( $isEven ) );
        $this->assertTrue( $container->any( $nameC ) );
        $this->assertTrue( $container->any( $alwaysTrue ) );

        $this->assertFalse( $container->every( $isEven ) );
        $this->assertFalse( $container->every( $nameC ) );
        $this->assertTrue( $container->any( $alwaysTrue ) );

        $container->remove($b);
        $this->assertFalse( $container->any( $isEven ) );

        $container->remove($a);
        $this->assertTrue( $container->every( $nameC ) );

        $container->remove($c);
        $this->assertTrue( $container->every( $alwaysTrue ) );
        $this->assertFalse( $container->every( $alwaysTrue, false ) );
        $this->assertFalse( $container->any( $alwaysTrue ) );

    }

}