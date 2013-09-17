<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\Tests;

use Bond\Container;
use Bond\Profiler;

class EntityContainerPerformance extends \PHPUnit_Framework_Testcase
{

    public function test_do_nothing()
    {
    }

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

    public function getRanges()
    {
        return array( 1, 20, 100, 200, 500, 750, 1000, 2500, 5000, 10000, 25000 );
    }

//    public function testFindBy()
//    {
//
//        $profiler = new Profiler( __FUNCTION__ );
//
//        $sets = array();
//        foreach( $this->getRanges() as $num ) {
//            $sets[$num] = $this->makeSet( $num );
//            $containers[$num] = new Container( $sets[$num] );
//        }
//
//        $profiler->log( );
//        // xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
//        // xhprof_enable();
//
//        foreach( $sets as $num => $set ) {
//            $find = (int) floor( $num / 2 );
//            $results = $containers[$num]->findById( $find );
//            $profiler->log( $num, $num );
//        }
//
//        // $data = new xhprof( xhprof_disable() );
//        //print_r( $data->output() );
//
//        echo $profiler->output();
//
//    }
//
//    public function testContainerGenerationTime()
//    {
//
//        $profiler = new Profiler( __FUNCTION__ );
//
//        $sets = array();
//        foreach( $this->getRanges() as $num ) {
//            $sets[$num] = $this->makeSet( $num );
//        }
//
//        foreach( $sets as $num => $set ) {
//            $container = new Container( $set, $set );
//            $profiler->log( $num, $num );
//        }
//
//    }
//
//    public function testSearchByArray()
//    {
//
//        $profiler = new Profiler( __FUNCTION__ );
//
//        $sets = array();
//        foreach( $this->getRanges() as $num ) {
//            $sets[$num] = $this->makeSet( $num );
//            $containers[$num] = new Container( $sets[$num] );
//        }
//
//
//        $profiler->log( );
//        foreach( $sets as $num => $set ) {
//
//            $containers[$num]->search( $sets[$num] );
//            $profiler->log( $num, $num );
//        }
//
//        echo $profiler->output();
//
//    }
//
//    public function testSearchByContainer()
//    {
//
//        $profiler = new Profiler( __FUNCTION__ );
//
//        $sets = array();
//        foreach( $this->getRanges() as $num ) {
//            $sets[$num] = $this->makeSet( $num );
//            $containers[$num] = new Container( $sets[$num] );
//        }
//
//
//        $profiler->log( );
//        foreach( $sets as $num => $set ) {
//
//            $containers[$num]->search( $containers[$num] );
//            $profiler->log( $num, $num );
//        }
//
//        echo $profiler->output();
//
//    }
//
//    public function testContainerRemovalTime()
//    {
//
//        $profiler = new Profiler( __FUNCTION__ );
//
//        $sets = array();
//        foreach( $this->getRanges() as $num ) {
//            $sets[$num] = $this->makeSet( $num );
//            $containers[$num] = new Container( $sets[$num] );
//        }
//
//        $profiler->log();
//        foreach( $sets as $num => $set ) {
//            $containers[$num]->remove( $containers[$num] );
//            $profiler->log( $num, $num );
//        }
//
//        echo $profiler->output();
//
//    }
//
//    public function testContainerContains()
//    {
//
//        $profiler = new Profiler( __FUNCTION__ );
//
//        $sets = array();
//        foreach( $this->getRanges() as $num ) {
//            $sets[$num] = $this->makeSet( $num );
//            $containers[$num] = new Container( $sets[$num] );
//        }
//
//        $profiler->log();
//        foreach( $sets as $num => $set ) {
//            $containers[$num]->contains( $containers[$num] );
//            $profiler->log( $num, $num );
//        }
//
//        echo $profiler->output();
//
//    }

}