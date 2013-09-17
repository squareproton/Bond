<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\MagicGetter;

class Event
{

    use MagicGetter;

    private $name;
    private $dispatchArgs = [];
    private $dispatchCount = 0;
    private $originalObject;
    private $when;

    public function __construct( $name, array $dispatchArgs, $originalObject )
    {
        $this->name = $name;
        array_unshift( $dispatchArgs, $this );
        $this->dispatchArgs = $dispatchArgs;
        $this->originalObject = $originalObject;
        $this->when = time();
    }

    public function dispatch( $callback, $incrementDispatchCount = 1 )
    {
        $this->dispatchCount = $this->dispatchCount + $incrementDispatchCount;
        return call_user_func_array(
            $callback,
            $this->dispatchArgs
        );
    }

}