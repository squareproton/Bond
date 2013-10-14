<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Exception;

class NoConverterFound extends \Exception
{
    public $postgresType;
    public function __construct( $postgresType )
    {
        $this->postgresType = $postgresType;
        $this->message = "No Bond\Pg\Converter found for postgres type `{$postgresType}`. Talk to Pete.";
    }
}