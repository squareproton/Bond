<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Exception\BadTypeException;
use Bond\MagicGetter;

class Flock implements \ArrayAccess, \Countable, \Iterator
{

    use MagicGetter;

    /**
     * The callable. Checks to check member qualclass / category of things we require
     * @var string
     */
    private $check;

    /**
     * Type
     */
    private $type;

    /**
     * The array of things contained in our flock
     * @var array
     */
    private $members = [];

    /**
     * @param $check class|object|callable Set the type and class of the objects we expect this flock to contain
     * @param array|obj Optional, contents
     */
    public function __construct( $check )
    {
        $this->makeCheckCallback( $check );
        // additional arguments?
        if( func_num_args() > 1 ) {
            $args = func_get_args();
            array_shift($args);
            call_user_func_array(
                array( $this, 'add' ),
                $args
            );
        }
    }

    /**
     * Sort a members of flock
     * @param Callable Sorting callback
     * @return self
     */
    public function sort( Callable $callback )
    {
        usort( $this->members, $callback );
        return $this;
    }

    /**
     * Merge a array of potential members into the flock replacing as required
     * @param array $members
     * @return Bond\Flock
     */
    public function merge( array $members )
    {
        foreach( $members as $offset => $value ) {
            $this->offsetSet( $offset, $value );
        }
        return $this;
    }

    /**
     * Add items to this flock
     * @return $this;
     */
    public function add()
    {
        $args = func_get_args();
        foreach( $args as $arg ) {
            if( is_array($arg) ) {
                foreach( $arg as $value ) {
                    $this[] = $value;
                }
            } else {
                $this[] = $arg;
            }
        }
        return $this;
    }

    /**
     * Does our flock contain a member
     * @param Object
     * @return bool
     */
    public function contains( $member )
    {
        return in_array( $member, $this->members, true );
    }

    /**
     * Remove a member from the flock
     * @param Member
     * @return Bond\Flock
     */
    public function remove( $member )
    {
        if( false !== $key = array_search( $member, $this->members, true ) ) {
            unset( $this->members[$key] );
        }
        return $this;
    }

    /**
     */
    public function map( Callable $callback )
    {
        return array_map( $callback, $this->flock );
    }

    public function any( Callable $callback )
    {
        foreach( $this->members as $member ) {
            if( call_user_func( $callback, $member ) ) {
                return true;
            }
        }
        return false;
    }

    public function every( Callable $callback )
    {
        foreach( $this->members as $member ) {
            if( !call_user_func( $callback, $member ) ) {
                return false;
            }
        }
        return (bool) $this->members;
    }

    // array access boiler plate
    public function offsetSet($offset, $value)
    {
        // type checkage
        if( !call_user_func( $this->check, $value ) ) {
            throw new BadTypeException( $value, "Wrong type" );
        }

        // check to see theat resolver isn't present
        if( false !== array_search( $value, $this->members, true) ) {
            return null;
        }

        // we're good to add
        if (is_null($offset)) {
            $this->members[] = $value;
        } else {
            $this->members[$offset] = $value;
        }
    }
    public function offsetExists($offset)
    {
        return isset($this->members[$offset]);
    }
    public function offsetUnset($offset)
    {
        unset($this->members[$offset]);
    }
    public function offsetGet($offset)
    {
        return isset($this->members[$offset]) ? $this->members[$offset] : null;
    }

    // countable boilerplate
    public function count()
    {
        return count( $this->members );
    }

    // iterable boilerplate
    public function rewind()
    {
        reset( $this->members );
    }
    public function current()
    {
        return current( $this->members );
    }
    public function key()
    {
        return key( $this->members );
    }
    public function next()
    {
        next( $this->members );
    }
    public function valid()
    {
        $key = key($this->members);
        return ( !is_null( $key ) && $key !== false );
    }

    /**
     * Make check callback for flock
     * @return Callable
     */
    private function makeCheckCallback( $check )
    {
        // callable
        if( is_callable($check) ) {
            $this->check = $check;
            $this->type = $this->makeCallbackHumanReadable( $check );
            return;
        }

        // class or obj
        if( is_object($check) ) {
            $class = get_class( $check );
        } elseif( is_string($check) ) {
            $class = (string) $check;
        } else {
            throw new BadTypeException( $check, "Class|Object|Callable");
        }
        $this->type = $class;
        $this->check = function( $newMember ) use ( $class ) {
            return is_a( $newMember, $class );
        };
    }

    /**
     * Return a string which is a best human readable representation the callback possible
     * @param Callable $callback
     * @return string
     */
    private function makeCallbackHumanReadable( Callable $callback )
    {
        if( is_string($callback) ) {
            return $callback;
        } elseif( is_array($callback) ) {
            return sprintf(
                "%s::%s",
                is_object( $callback[0] ) ? get_class($callback[0]) : $callback[0],
                $callback[1]
            );
        } elseif( !($callback instanceof \Closure) ) {
            return "<closure>";
        }
        throw new \Exception("Wow. I didn't know php could define a callable of this type. Pete __definately__ wants to know all about this.");
    }

}