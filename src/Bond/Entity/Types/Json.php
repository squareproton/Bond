<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

use Bond\Exception\BadTypeException;
use Bond\Exception\UnknownOptionException;
use Bond\Exception\BadJsonException;

use Bond\Pg\Connection;

/**
 * @author Pete
 */
class Json implements SqlInterface, \JsonSerializable
{

    # validation
    const VALIDATE_EXCEPTION = 0;
    const VALIDATE_STRIP = 1;
    const VALIDATE_DISABLE = 2;

    /**
     * Valid json string
     */
    private $json;

    /**
     * Json decoded representation of our string
     */
    private $decoded;

    /**
     * Is json object invalid.
     * null if object hasn't been checked yet
     * true is object is valid json
     * instance of BadJsonExceptionException if object is bad json
     */
    private $isValid;

    /**
     * Is a object with takes a valid string json representation
     * @param string JSON
     * @param int Validation strategy
     */
    public function __construct( $input, $inputValidate = self::VALIDATE_EXCEPTION )
    {
        if( !is_string($input) ) {
            throw new BadTypeException( $input, 'string' );
        }

        $this->json = $input;

        switch( true ) {

            case self::VALIDATE_EXCEPTION === $inputValidate:
            case self::VALIDATE_STRIP === $inputValidate:
                if( !$this->isValid($exception) ) {
                    // not quite sure what the behaviour should be here.
                    // at the moment just reset this to a empty json object
                    if( $inputValidate == self::VALIDATE_STRIP ) {
                        $this->json = json_encode(null);
                        $this->isValid = null;
                    // throw exception
                    } else {
                        throw $exception;
                    }
                }
                break;
            case self::VALIDATE_DISABLE === $inputValidate:
                break;
            default:
                throw new UnknownOptionException(
                    $inputValidate,
                    [ self::VALIDATE_EXCEPTION, self::VALIDATE_STRIP, self::VALIDATE_DISABLE ]
                );
        }
    }

    /**
     * Static entry point to generate a json_object from something that can be json_serialized
     * We don't need to validate or calc the json representation as this can be calculated lazily
     * @return Bond\Entity\Type\Json
     */
    public static function makeFromObject( $object )
    {
        // inistante object without going via constructer to allow us to
        $refl = new \ReflectionClass( __CLASS__ );
        $obj = $refl->newInstanceWithoutConstructor();
        $obj->isValid = true;
        $obj->decoded = $object;
        return $obj;
    }

    /**
     * Does or json object contain valid json
     * @return bool
     */
    public function isValid( &$exception = null )
    {
        $exception = null;
        // have we validated this before?
        if( $this->isValid === null ) {
            $this->decoded = @json_decode( $this->json, true );
            // have error?
            if( null === $this->decoded and JSON_ERROR_NONE !== $lastError = json_last_error() ) {
                // yep
                $this->isValid = new BadJsonException( $this->json, $lastError );
                $exception = $this->isValid;
            } else {
                // nope
                $this->isValid = true;
            }
        // is the isValid propety a exception
        } elseif ( $this->isValid !== true ) {
            $exception = $this->isValid;
        }

        return $this->isValid === true;
    }

    /**
     * The JSON decoded representation of our string. Cached.
     * return mixed
     */
    public function get()
    {
        if( !$this->isValid($exception) ) {
            throw $exception;
        }
        return $this->decoded;
    }

    /**
     * Interface for JsonSerializable
     * See http://php.net/manual/en/jsonserializable.jsonserialize.php
     */
    public function jsonSerialize()
    {
        return $this->get();
    }

    /**
     * __toString. Returns valid json.
     * Objects instantiated with initFromObject are lazily json_encoded
     * @return string
     */
    public function __toString()
    {
        // have json or are we working off decoded
        if( $this->json ) {
            return $this->json;
        // objects are stored by reference and can change, json encode every single time
        } elseif( is_object($this->decoded) ) {
            return json_encode( $this->decoded );
        } else {
            $this->json = json_encode( $this->decoded );
            return $this->json;
        }
    }

    /**
     * Get a pretty printed version of our json encoded string
     */
    public function getPretty()
    {
        return json_encode( $this->get(), JSON_PRETTY_PRINT );
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoting )
    {
        return $quoting->quote( $this->__toString() );
    }

}