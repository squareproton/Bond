<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database\Exception;

class UnknownEnumException extends \Exception
{
    public $enum;
    public function __construct( $enum )
    {
        $this->enum = $enum;
        $this->message = "Unknown enum `{$enum}`";
    }
}