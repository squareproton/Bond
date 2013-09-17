<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Event\EmitterMinimal;

class Debug
{

    use EmitterMinimal;

    // debuggin levels
    const SEVERE = 0;
    const ERROR  = 1;
    const EXCEPTION = 2;
    const WARNING = 3;
    const INFO = 4;
    const ENTRY = 5;
    const DEBUG = 6;

    /**
     * Callable
     * @mixed
     */
    private static $template = '\Bond\Debug';

    /**
     * Some user identifyable string
     * @var String
     */
    public $name;

    /**
     * Timings
     */
    public $timings = [];

    /**
     * Standard constructor
     */
    public function __construct( $name = null )
    {
        $this->name = $name;
        $this->timings['INSTANTIATION'] = microtime(true);
    }

    /**
     * See self::$template
     * @return void
     */
    public static function setTemplate($template)
    {
        self::$template = $template;
    }

    /**
     * Get a debugger object
     * @return Bond\Debug
     */
    public static function get()
    {
        return (new \ReflectionClass(self::$template))->newInstanceArgs( func_get_args() );
    }

    // These functions are pretty crap and are only here to aid in some basic debugging.
    // Eventually this kind of shit should be tied in with and exception handling routines and a more robust solution
    public function start()
    {
        $this->on(
            DEBUG::INFO,
            function( $e, $event, $data ) {
                echo $data . "\n";
            }
        );
    }

    public function stop()
    {
        $this->removeListner(Debug::INFO);
    }

}