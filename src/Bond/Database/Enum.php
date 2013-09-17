<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database;

use Bond\Database\Exception\UnknownEnumException;
use Bond\MagicGetter;

class Enum
{

    use MagicGetter;

    /**
     * Enum options. Keyed by name
     * @var array
     */
    private $enums = [];

    public function __construct( array $enums )
    {
        $this->enums = $enums;
    }

    /**
     * Public accessor for enum options
     * @param string
     * @return array Allowed values
     */
    public function getValues( $name )
    {
        if( !isset( $this->enums[$name] ) ) {
            throw new UnknownEnumException($name);
        }
        return $this->enums[$name];
    }

    /**
     * Register a enum with our provider
     * @param string EnumName
     * @param array EnumOptions
     */
    public function register( $name, array $options )
    {
        $this->enums[$name] = $options;
    }

    /**
     * Is valid check
     * @param string enum name
     * @param string value
     * @return bool
     */
    public function isValid( $name, $value = null )
    {
        if( !isset( $this->enums[$name] ) ) {
            return false;
        } elseif( $value === null ) {
            return true;
        }
        return !isset( $value ) || in_array( $value, $this->enums[$name] );
    }

    /**
     * Get a random value from a enum. Primarily used by the import script
     * @return string
     */
     public function getRandomValue( $name )
     {
        if( !isset( $this->enums[$name] ) ) {
            throw new UnknownEnumException($name);
        }
        return $this->enums[$name][array_rand($this->enums[$name])];
     }

}