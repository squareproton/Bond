<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database\Exception;

class UnableToConnectException extends \Exception
{
    public $connectionString;
    public $error;
    public $settings;
    public function __construct( $connectionString, $error, array $settings )
    {
        $this->connectionString = $connectionString;
        $this->error = $error;
        $this->settings = $settings;
        $this->message = sprintf(
            "Error: %s. Couldn't connect to database with `%s`.",
            $this->error,
            $this->connectionString
        );
    }
}