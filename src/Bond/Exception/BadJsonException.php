<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

use Bond\Exception\BadTypeException;

class BadJsonException extends BadTypeException
{

    // see http://fr2.php.net/manual/en/function.json-last-error.php
    protected static $errors = array(
        JSON_ERROR_NONE => "No error has occurred",
        JSON_ERROR_DEPTH => "The maximum stack depth has been exceeded",
        JSON_ERROR_STATE_MISMATCH => "Invalid or malformed JSON",
        JSON_ERROR_CTRL_CHAR => "Control character error, possibly incorrectly encoded",
        JSON_ERROR_SYNTAX => "Syntax error",
        JSON_ERROR_UTF8 => "Malformed UTF-8 characters, possibly incorrectly encoded PHP 5.3.3",
    );

    public $jsonError;

    public function __construct( $supposedJson = null, $jsonError )
    {

        $this->var = $supposedJson;
        $this->type = 'json';
        $this->json = $supposedJson;

        $this->message = $this->getJsonErrorHuman();

    }

    public function getJsonErrorHuman()
    {

        if( isset( self::$errors[$this->jsonError] ) ) {
            return self::$errors[$this->jsonError];
        } else {
            return "Unknown JSON error occoured";
        }

    }

}