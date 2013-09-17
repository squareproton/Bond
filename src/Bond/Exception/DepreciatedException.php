<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

use Bond\BackTrace;

class DepreciatedException extends \Exception
{
    public function __construct()
    {

        $trace = (new BackTrace($this))->getSane();

        /* Why does this sometimes happen? Everything should have a trace, fucking thing sucks.
        if( !isset($trace[0] ) ) {
            die("fuck");
        }
        */
        $source = isset( $trace[0]['class'] ) ? $trace[0]['class'] : $trace[0]['file'];
        $line = $trace[0]['line'];
        $this->message = "Called from {$source} @ line {$line}. \n";
    }
}