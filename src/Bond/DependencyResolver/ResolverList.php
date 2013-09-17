<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver;

use Bond\DependencyResolver\DependencyResolverInterface;
use Bond\DependencyResolver\Exception\IdCollisionException;
use Bond\Exception\BadTypeException;

class ResolverList implements \ArrayAccess, \Countable, \Iterator
{

    private $container = [];

    public function __construct( array $resolvers = array() )
    {
        foreach( $resolvers as $resolver ) {
            $this->offsetSet( null, $resolver );
        }
    }

    public function getIds()
    {
        $output = [];
        foreach( $this->container as $resolver ) {
            $output[] = $resolver->getId();
        }
        return $output;
    }

    public function __toString()
    {
        return json_encode( $this->getIds(), JSON_PRETTY_PRINT );
    }

    public function shuffle()
    {
        shuffle( $this->container );
        return $this;
    }

    // utility methods
    public function getById($id)
    {
        foreach( $this->container as $resolver ) {
            if( $resolver->getId() == $id ) {
                return $resolver;
            }
        }
        return null;
    }

    // contains
    public function contains( DependencyResolverInterface $resolver )
    {
        return in_array( $resolver, $this->container, true );
    }
    public function containsId($id)
    {
        return !is_null( $this->getById($id) );
    }

    // remove
    public function remove( DependencyResolverInterface $resolver )
    {
        if( false !== $key = array_search( $resolver, $this->container, true ) ) {
            unset( $this->container[$key] );
        }
        return $this;
    }
    public function removeById($id)
    {
        // does two loops but abstracts the getById code
        if( null !== $resolver = $this->getById($id) ) {
            $this->remove( $resolver );
        }
        return $this;
    }

    // array access boiler plate
    public function offsetSet($offset, $value)
    {
        // type check
        if( !($value instanceof DependencyResolverInterface) ) {
            throw new BadTypeException( $value, "\Bond\DependencyResolver\DependencyResolverInterface" );
        }
        // check to see theat resolver isn't present
        if( false !== array_search( $value, $this->container, true) ) {
            return null;
        }
        // check resolver name is unique
        if( $this->containsId($value->getId()) ) {
            throw new IdCollisionException( $this, $value );
        }

        // we're good to add
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }
    public function offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    // countable boilerplate
    public function count()
    {
        return count( $this->container );
    }

    // iterable boilerplate
    public function rewind()
    {
        reset( $this->container );
    }
    public function current()
    {
        return current( $this->container );
    }
    public function key()
    {
        return key( $this->container );
    }
    public function next()
    {
        next( $this->container );
    }
    public function valid()
    {
        $key = key($this->container);
        return ( !is_null( $key ) && $key !== false );
    }

}