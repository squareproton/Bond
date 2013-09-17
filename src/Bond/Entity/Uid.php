<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity;

/**
 * Base class for managing, manipulating and saving objects
 */
class Uid implements \Countable
{

    # access
    const LAST = '__CURRENT';
    const NEXT = '__NEXT';

    # configuration
    const START_AT = 0;
    const INCREMENT = -1;

    const SERIALIZATION_PREFIX = '__KEY__';

    /**
     * Store of assigned keys
     * @var array
     */
    private static $assigned = array();

    /**
     * A uniq key
     * @var numeric (although most likely a int)
     */
    private $key = null;

    /**
     * Type of key
     * @var scalar
     */
    private $type = null;

    /**
     * A generate a self incrementing uniq key based on a key
     * @param <type> $type
     * @return <type>
     */
    public function __construct( $type )
    {

        if( is_object( $type ) ) {
            $type = get_class( $type );
        }

        if( isset( self::$assigned[$type] ) ) {
            self::$assigned[$type] = self::$assigned[$type] + self::INCREMENT;
        } else {
            self::$assigned[$type] = self::START_AT;
        }

        $this->key = self::$assigned[$type];
        $this->type = $type;

    }

    /**
     * Standard getter
     */
    public function __get( $key )
    {
        switch( $key ) {
            case 'key':
            case 'type':
                return $this->$key;
        }
        throw new \InvalidArgumentException("Don't know about key `{$key}`");
    }

    /**
     * Return our unique key
     * @return string
     */
    public function __toString()
    {
        return self::SERIALIZATION_PREFIX . $this->key;
    }

    /**
     * How many keys have been instantiated so far
     * @return int
     */
    public function count()
    {
        return ( self::$assigned[$this->type] - self::START_AT + self::INCREMENT ) / self::INCREMENT;
    }

}