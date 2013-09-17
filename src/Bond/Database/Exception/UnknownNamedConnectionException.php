<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database\Exception;

class UnknownNamedConnectionException extends \Exception
{
    public $connection;
    public function __construct( $connection )
    {
        $this->connection = $connection;
        $this->message = "Settings not found for named database connection `{$connection}`.";
    }
}