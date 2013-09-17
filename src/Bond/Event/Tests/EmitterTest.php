<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Event\Tests;

use Bond\Event\Emitter;

class ET {
    use Emitter;
}

class EmitterTest extends \PHPUnit_Framework_Testcase
{

    public function testETAdd()
    {

        $et = new ET();
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

    public function testEventCallbacks()
    {

        $et = new ET();
        $et->c = 0;

        $inc_c = function() use ( $et ) {
            $et->c++;
        };

        $checker = function($event) {
            return $event->name === 'ok';
        };

        // test checker
        $et->on( $checker, $inc_c );
        $et->emit('ok');
        $et->emit('nope');
        $this->assertSame( $et->c, 1 );

        // test removal
        $et->removeListener($checker, $inc_c);
        $et->emit('ok');
        $this->assertSame( $et->c, 1 );

        // test removeAllListeners
        $et->on( $checker, $inc_c );
        $et->emit('ok');
        $this->assertSame( $et->c, 2 );
        $et->removeAllListeners( $checker );
        $et->emit('ok');
        $this->assertSame( $et->c, 2 );

    }

    public function testOnce()
    {

        $et = new ET();

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

        $et = new ET();
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

    public function testSetTimeout()
    {

        $et = new ET();

        $output = [];

        $et->setTimeout(
            function($e) use (&$output) {
                $output[] = 'one';
            },
            0.05
        );
        $et->setTimeout(
            function($e) use (&$output) {
                $output[] = 'two';
            },
            0.03
        );

        $et->setTimeout(
            function($e) use ($et) {
                $this->assertSame( $e->originalObject, $et );
            },
            0.03
        );

        $this->assertSame( 3, count( $et->timeouts() ));

        for ( $i = 0; $i < 7; $i++ ) {
            $output[] = $i;
            usleep(10000);
            $et->tick();
        }

        $this->assertSame( 0, count( $et->timeouts() ));
        $this->assertSame( $output, [0,1,2,'two',3,4,'one',5,6] );

    }

    public function testSetInterval()
    {

        $et = new ET();

        $output = [];

        $interval = $et->setInterval(
            function($e) use (&$output) {
                $output[] = 'one';
            },
            0.01
        );

        for ( $i = 0; $i < 10; $i++ ) {
            usleep(10000);
            $et->tick();
        }
        $this->assertSame( $output, array_fill(0,10,'one') );

        // remove the interval - nothing should happen
        $et->removeInterval($interval);
        for ( $i = 0; $i < 10; $i++ ) {
            usleep(10000);
            $et->tick();
        }
        $this->assertSame( $output, array_fill(0,10,'one') );

     }

}