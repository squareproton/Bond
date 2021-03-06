<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\Tests;

use Bond\Container\ContainerableInterface;

class EBTC implements ContainerableInterface
{
    public $id;
    public $name;
    function __construct( $id = null, $name = null )
    {
        $this->id = $id;
        $this->name = $name;
    }
}