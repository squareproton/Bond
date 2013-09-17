<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Tests;

use Bond\Flock;

class FlockTest extends \PHPUnit_Framework_Testcase
{

    public function testInit()
    {

        $flock = new Flock( new \stdClass() );

        $flock[] = $obj = new \stdClass();
        $this->assertSame( count($flock), 1 );
        $this->assertTrue( $flock->contains($obj) );
        $this->assertFalse( $flock->contains(new \stdClass()) );

        // adding object twice does fuck all
        $flock[] = $obj;
        $this->assertSame( count($flock), 1 );

        // count increments
        $flock[] = $obj = new \stdClass();
        $this->assertSame( count($flock), 2 );
        $this->assertTrue( $flock->contains($obj) );
        $this->assertSame( $flock->remove($obj), $flock );
        $this->assertFalse( $flock->contains($obj) );

        $flock2 = new Flock( "stdClass", new \stdClass(), new \stdClass() );
        $this->assertSame( count($flock2), 2 );

    }

    public function testInitWithCallbackCheck()
    {
        $flock = new Flock('is_int');

        $flock[] = 1;
        $flock[] = 2;

        $this->setExpectedException('Bond\Exception\BadTypeException');
        $flock[] = "1";
    }

    public function testInitWithCallbackCheckDuplicateValues()
    {
        $flock = new Flock('is_int');

        $flock[] = 1;
        $flock[] = 1;

        $this->assertSame( count($flock), 1 );
    }

    public function testSort()
    {
        $sortFn = function( $a, $b ) {
            if( $a < $b ) {
                return -1;
            } elseif( $a > $b ) {
                return 1;
            }
            return 0;
        };

        $flock = new Flock('is_int');
        $flock[] = 2;
        $flock[] = 1;

        $this->assertSame( $flock[0], 2 );
        $this->assertSame( $flock[1], 1 );
        $flock->sort($sortFn);

        $this->assertSame( $flock[0], 1 );
        $this->assertSame( $flock[1], 2 );

    }

}