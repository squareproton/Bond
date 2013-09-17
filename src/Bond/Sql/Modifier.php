<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

use Bond\Exception\BadTypeException;
use Bond\Exception\Depreciated;

use Bond\MagicGetter;

use Bond\Sql\Exception\UnknownQueryModifier;
use Bond\Sql\Identifier;
use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;
use Bond\Sql\SqlPassthroughInterface;

class Modifier
{

    use MagicGetter;

    public $quoteInterface;

    private $events = [];
    private $type;
    private $isArray;

    public function __construct( QuoteInterface $quoteInterface, $type, $isArray )
    {
        $this->quoteInterface = $quoteInterface;
        $this->type = $type;
        $this->isArray = $isArray;
    }

    public function exec( $value )
    {
        if( is_object( $value ) and ( $value instanceof SqlPassthroughInterface ) ) {
            return $value->parse( $this->quoteInterface );
        }

        $value = $this->coearceToType($value);

        if( isset( $this->events['pre'] ) ) {
            foreach( $this->events['pre'] as $modifier ) {
                $value = call_user_func( $modifier, $value );
            }
        }

        if( $value instanceof SqlInterface ) {
            $value = $value->parse( $this->quoteInterface );
        } else {
            $value = $this->quoteInterface->quote( $value );
        }

        if( isset( $this->events['post'] ) ) {
            foreach( $this->events['post'] as $modifier ) {
                $value = call_user_func( $modifier, $value );
            }
        }

        return $value;

    }

    public function add( $event, Callable $modifier )
    {
        if( isset( $this->events[$event] ) ) {
            $this->events[$event][] = $modifier;
        } else {
            $this->events[$event] = [ $modifier ];
        }
    }

    /**
     * Coearce passed value to type stored in $this->type.
     * The output of this function is expected to be passed to QuoteInterface->quote()
     *
     * @param mixed Value to coearce to type
     * @return mixed
     */
    private function coearceToType( $value )
    {

        // objects have to support QueryQuoteSafe
        if( is_object( $value ) ) {

            if( $value instanceof SqlInterface ) {
                return $value;
            }

            throw new BadTypeException( $value, "Needs to implement SqlInterface" );

        // null's don't get molested
        } elseif ( $value === null ) {
            return null;
        }

        $isArray = is_array( $value );

        // We're told to expect a array and we don't get one ...
        if( $this->isArray and !$isArray ) {
            throw new BadTypeException( $value, 'array' );
        }

        // Have array ...
        if( is_array( $value ) ) {

            // build a element modifier for the elenents in our array
            $elementModifier = clone $this;
            if( in_array( $this->type, ['in','array'], true ) ) {
                $elementModifier->type = null;
            }
            $elementModifier->isArray = false;

            // coearce every value in the array to to the correct type
            $value = array_map(
                array( $elementModifier, 'coearceToType' ),
                $value
            );

            $class = sprintf(
                "%s\\%sType",
                __NAMESPACE__,
                 $this->type === 'in' ? 'In' : 'Array'
            );

            return new $class( $value );

        }

        // standard types
        switch( $this->type ) {

            case null:
                return $value;
            case 'text':
            case 'enum':
            case 'timestamp':
                return (string) $value;
            case 'bool':
                return \Bond\boolval( $value );
            case 'oid':
            case 'int':
                return (int) $value;
            case 'identifier':
                return new Identifier( $value );
            case 'json':
                return is_string($value) ? $value : json_encode($value);
            case 'varbit':
                return decbin( (int) $value );
            case 'bytea':
                return new Bytea( $value );

        }

        // char(13)
        if( preg_match( '/^char\\(([\d]+)\\)$/', $this->type, $matches ) ) {
            return (string) substr( $value, 0, $matches[1] );
        }

        return $value;

    }

    /**
     * Return closure which casts a variable to a type
     * @param string type
     * @return string
     */
    public function generateCastClosure( $type )
    {
        return function( $value ) use ( $type ) {
            return $value . "::" . $type;
        };
    }

}