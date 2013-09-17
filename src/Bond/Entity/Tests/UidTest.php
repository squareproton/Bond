<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Tests;

use Bond\Entity\Uid;

class UidTest extends \PHPUnit_Framework_Testcase
{

    public function testUid()
    {

        $key = new Uid( null );
        $this->assertSame( $key->key, Uid::START_AT );
        $this->assertSame( count( $key ), 1 );

        $key = new Uid( null );
        $this->assertSame( $key->key, Uid::START_AT + Uid::INCREMENT );
        $this->assertSame( count( $key ), 2 );

        $key = new Uid( 'spanner' );
        $this->assertSame( $key->key, Uid::START_AT );
        $key = new Uid( 'spanner' );
        $this->assertSame( $key->key, Uid::START_AT + Uid::INCREMENT );

    }

    public function testUidWithObject()
    {

        foreach( range(1, 100) as $n ) {
            $key = new Uid( new \stdClass() );
        }

        $this->assertSame( count( $key ), 100 );

    }

}