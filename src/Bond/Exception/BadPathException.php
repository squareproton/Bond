<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class BadPathException extends \Exception
{
    public $path;
    public function __construct( $path )
    {
        $this->path = $path;
        $this->message = "Path `{$path}` isn't valid path";
    }
}