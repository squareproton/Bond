<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Event;

use Bond\Event;

trait EmitterMinimal
{

    private $listeners = [];
    private $listenersOnce = [];
    private $eventDebug = false;

    public function eventDebug($state)
    {
        $this->eventDebug = (bool) $state;
    }

    public function addListener($event, $listener)
    {
        $this->listeners[$event][] = $listener;
        return $this;
    }

    public function on($event, $listener)
    {
        return $this->addListener($event, $listener);
    }

    public function once($event, $listener)
    {
        $this->listenersOnce[$event][] = $listener;
        return $this;
    }

    // this only removes up to one listener
    // if listener is repeated need to call multiple times
    // I think this is the behaviour we want if not use array_keys
    public function removeListener($event, $listener)
    {
        // shortcut to only remove a listener if they exist
        // having this allows us to simplify calling code
        if( !isset($listener) ) {
            return $this;
        }

        // listeners
        if (isset($this->listeners[$event])) {
            if (false !== ($key = array_search($listener, $this->listeners[$event]))) {
                unset($this->listeners[$event][$key]);
            }
            if( !$this->listeners[$event] ) {
                unset( $this->listeners[$event] );
            }
        }
        // listeners once
        if (isset($this->listenersOnce[$event])) {
            if (false !== ($key = array_search($listener, $this->listenersOnce[$event]))) {
                unset($this->listenersOnce[$event][$key]);
            }
            if( !$this->listenersOnce[$event] ) {
                unset( $this->listenersOnce[$event] );
            }
        }
        return $this;
    }

    public function removeAllListeners( $event = null )
    {
        if( $event === null ) {
            $this->listeners = [];
            $this->listenersOnce = [];
        }
        return $this;
    }

    public function listeners( $event )
    {
        if( isset( $this->listeners[$event], $this->listenersOnce[$event] ) ) {
            return array_merge( $this->listeners[$event], $this->listenersOnce[$event] );
        } elseif ( isset( $this->listeners[$event] ) ) {
            return $this->listeners[$event];
        } elseif ( isset( $this->listenersOnce[$event] ) ) {
            return $this->listenersOnce[$event];
        } else {
            return [];
        }
    }

    public function emit( $eventName )
    {

        if( !isset($this->listeners[$eventName]) and !isset($this->listenersOnce[$eventName]) ) {
            // not sure about this
            return null;
        }

        $args = func_get_args();
        array_shift( $args );
        $event = new Event( $eventName, $args, $this );

        if( isset( $this->listeners[$eventName] ) ) {
            foreach( $this->listeners[$eventName] as $listener ) {
                $event->dispatch($listener);
            }
        }
        if( isset( $this->listenersOnce[$eventName] ) ) {
            foreach( $this->listenersOnce[$eventName] as $key => $listener ) {
                $event->dispatch($listener);
                unset( $this->listenersOnce[$eventName][$key] );
            }
        }

        if( $this->eventDebug ) {
            printf(
                "%s %s %s",
                $event->name,
                $event->dispatchCount,
                json_encode( $event->dispatchArgs )
            );
        }

        return $event;
    }

}