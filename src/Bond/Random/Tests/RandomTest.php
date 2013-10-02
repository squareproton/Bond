<?php

namespace Bond\Random\Tests;

use Bond\Random;

class RandomTest extends \PHPUnit_Framework_Testcase
{

    public function testArrayLoop()
    {
        $arrayLoop = new Random\ArrayLoop(range(0,4));
        $output = [];
        $answer = [];
        for( $c = 0; $c < 10; $c++ ) {
            $output[] = $arrayLoop();
            $answer[] = $c % 5;
        }
        $this->assertSame( $answer, $output );
    }

    public function testArrayWeighted()
    {
        $arrayWeighted = new Random\ArrayWeighted(
            [0,1,2,3,4,5,6,7,8,9],
            [0,1,2,3,4,5,6,7,8,9]
        );

        // the expected averate of this array should be (3*0.2) + ( 2*0.3) + (1*0.5) = 1.2
        $counts = array_combine(
            [0,1,2,3,4,5,6,7,8,9],
            [0,0,0,0,0,0,0,0,0,0]
        );

        for( $c = 0; $c < 100000; $c++ ) {
            $counts[$arrayWeighted()]++;
        }

        for( $c = 1; $c < count($counts); $c++ ) {
            $this->assertSame(
                (int) round($counts[$c] / $counts[1]),
                $c
            );
        }
    }

    public function testNullify()
    {

        $one = new Random\Callback(function(){return 2;});
        $nullify = new Random\Nullify($one, 0.5);

        $total = 0;
        $c = 0;
        while( $c++ < 500000 ) {
            $total += $nullify();
        }

        $this->assertSame(
            round( $total / ($c/100) ),
            round( $c / ($c/100) )
        );

    }

    public function testRemainder()
    {

        $arraysimple = new Random\ArraySimple( [1,2,3] );
        $arraysimple();

        $callback = new Random\Callback(function(){return 1;});
        $this->assertSame( $callback(), 1 );

        $implode = new Random\Implode( $callback, $callback );
        $this->assertSame( $implode(','), '1,1' );

        $implode = new Random\Implode( $callback, $callback );
        $this->assertSame( $implode(','), '1,1' );

        $range = new Random\Range( 1, 10 );

        $string = new Random\String( 'abcdefghijklmnopqrstuvwz', 100, 100 );

        $time = new Random\Time( time()-1000, time(), 10 );
        $time();

    }

}