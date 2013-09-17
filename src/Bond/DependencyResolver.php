<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\DependencyResolver\DependencyResolverInterface;
use Bond\DependencyResolver\ResolverList;

use Bond\DependencyResolver\Exception\CircularDependencyException;

class DependencyResolver implements DependencyResolverInterface
{

    protected $id;

    protected $resolve;

    protected $depends;

    protected $options;

    public function __construct( $id, $resolve, array $options = array() )
    {
        $this->id = $id;
        if( !is_callable($resolve) ) {
            throw new \InvalidArgumentException("DependencyResolver needs a resolution callback.");
        }
        $this->resolve = $resolve;
        $this->depends = new ResolverList();

        // Scope any event callbacks to this object
        // allows a closure which uses $this-> to work as you might expect! 5.4 awesome
        // WARNING! If you pass a closure which is already scoped this will alter its behaviour.
        foreach( ['PRE_RESOLVE', 'POST_RESOLVE'] as $event ) {
            if( isset( $options[$event] ) and $options[$event] instanceof \Closure ) {
                $options[$event] = \Closure::bind( $options[$event], $this, get_called_class() );
            }
        }
        $this->options = $options;

    }

    public function __toString()
    {
        return (string) $this->id;
    }

    public function getDepends()
    {
        return $this->depends;
    }

    public function getId()
    {
        return $this->id;
    }

    public function resolve( ResolverList $resolved, ResolverList $unresolved, $performResolution = true )
    {
        // already resolved
        if( $resolved->contains($this) ) {
            return true;
        }
        if( $unresolved->contains($this) ) {
            throw new CircularDependencyException( $unresolved, $this );
        }
        // add to unresolved list whilst we sort out dependencies
        $unresolved[] = $this;
        foreach( $this->depends as $dependency ) {
            $dependency->resolve( $resolved, $unresolved, $performResolution );
        }
        // all dependencies resolved so we can execute our code
        $unresolved->remove( $this );
        if( $performResolution ) {
            // pre resolve callback
            if( isset( $this->options['PRE_RESOLVE'] ) ) {
                call_user_func( $this->options['PRE_RESOLVE'] );
            }
            $output = call_user_func( $this->resolve );
            // post resolution callback
            if( isset( $this->options['POST_RESOLVE'] ) ) {
                call_user_func( $this->options['POST_RESOLVE'] );
            }
        }
        $resolved[] = $this;
        return $output;
    }

    public function addDependency( DependencyResolverInterface $dependency )
    {
        $this->depends[] = $dependency;
    }

}