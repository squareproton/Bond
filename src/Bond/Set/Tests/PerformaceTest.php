<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Set\Tests;

use Bond\Set\Text;
use Bond\Set\Integer;

use Bond\Profiler;

class SetPerformaceTest extends \PHPUnit_Framework_Testcase
{

    // performance tests are slow and mostly these will be commented out
    // all test classes must have at least one test
    public function testDoNothing()
    {
    }

    public function getRanges( $max = null)
    {
        $output = array();
        foreach( array( 1, 2, 5, 20, 100, 200, 500 , 750, 1000, 2500, 5000, 10000, 25000 ) as $n ) {
            if( $max and $n > $max ) {
                break;
            }
            $output[] = $n;
        }
        return $output;
    }

    /*
    public function testAddContinuousRange()
    {

        $i = new Integer();

        $profiler = new Profiler( __FUNCTION__ );

        $sets = array();
        foreach( $this->getRanges(25000) as $num ) {
            $sets[$num] = range(1, $num);
            shuffle( $sets[$num] );
        }

        $profiler->log();
        foreach( $sets as $num => $set ) {

            $integer = new Integer( $set );
            $profiler->log( $num, $num );
        }

        echo $profiler->output();

    }

    public function testAddFuckedRange()
    {

        $i = new Integer();

        $profiler = new Profiler( __FUNCTION__ );

        $sets = array();
        foreach( $this->getRanges() as $num ) {
            $half = ceil( $num / 5 );
            $sets[$half] = range(1, $num);
            // shuffle( $sets[$half] );
            array_reverse( $sets[$half] );

            $sets[$half] = array_slice( $sets[$half], $half );
        }

        $profiler->log();
        foreach( $sets as $num => $set ) {
            $integer = new Integer( $set );
            $profiler->log( $num, $num );
        }

        echo $profiler->output();

    }
    */

}