<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Event\Tests;

use Bond\Event\EmitterMinimal;

class ETM {
    use EmitterMinimal;
}

class EmitterMinimalTest extends \PHPUnit_Framework_Testcase
{

    public function testETAdd()
    {

        $et = new ETM();
        $et->c = 0;

        $eat = function() use ( $et ) {
            $et->c++;
        };
        $this->assertSame( $et, $et->on( 'eat', $eat ) );

        $et->emit('eat');
        $this->assertSame( $et->c, 1);

        $et->emit('eat');
        $this->assertSame( $et->c, 2);

        $et->removeListener('eat', $eat );
        $this->assertSame( $et->c, 2);

        $et->on('eat', $eat );
        $et->on('eat', $eat );
        $et->emit('eat');
        $this->assertSame( $et->c, 4);

        $et->removeAllListeners('noteat');
        $et->emit('eat');
        $this->assertSame( $et->c, 6);

        $et->removeAllListeners();
        $et->emit('eat');
        $this->assertSame( $et->c, 6);

    }

    public function testOnce()
    {

        $et = new ETM();

        $c = 0;
        $sleep = function() use ( &$c ) {
            $c++;
        };

        $this->assertSame( $et, $et->once( 'sleep', $sleep ) );
        $this->assertSame( count( $et->listeners('sleep') ), 1 );
        $et->emit( 'sleep' );
        $this->assertSame( count( $et->listeners('sleep') ), 0 );
        $this->assertSame( $c, 1 );
        $et->emit( 'sleep' );
        $et->emit( 'sleep' );
        $this->assertSame( $c, 1 );

    }

    public function testEmitArguments()
    {

        $et = new ETM();
        $et->on(
            'wash',
            function ( $event, $one, $two )  use ( $et ) {
                $this->assertSame( $event->originalObject, $et );
                $this->assertSame( $event->name, 'wash' );
                $this->assertSame( $one, 'one' );
                $this->assertSame( $two, 'two' );
            }
        );
        $et->emit('wash', 'one', 'two' );

    }

}