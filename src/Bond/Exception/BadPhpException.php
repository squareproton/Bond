<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class BadPhpException extends \Exception
{
    public $php;
    public $errors;
    public function __construct( $php, $errors )
    {
        $this->php = $php;
        $this->errors = $errors;
        $this->message = "BadPhp: {$errors}";
    }
}