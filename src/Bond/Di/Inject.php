<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di;

class Inject
{
    private $name;

    function __construct($name = null)
    {
        $this->name = $name;
    }

    function getName()
    {
        return $this->name;
    }

    function __toString() { return "<Inject>"; }

}
