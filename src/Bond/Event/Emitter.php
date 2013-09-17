<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Event;

use Bond\Event;

trait Emitter
{

    private $listeners = [];
    private $listenersOnce = [];
    private $listenersCallback = [];
    private $timeouts = [];
    private $intervals = [];
    private $eventDebug = false;

    public function eventDebug($state)
    {
        $this->eventDebug = (bool) $state;
    }

    public function addListener($event, $listener)
    {
        if( $event instanceof \Closure ) {
            $this->listenersCallback[] = [$event, $listener];
        } else {
            $this->listeners[$event][] = $listener;
        }
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
        // listenersCallback
        if( $event instanceof \Closure ) {
            foreach( $this->listenersCallback as $key => $value ) {
                // does the event and listener match?
                if( $value[0] === $event && $value[1] === $listener ) {
                    unset( $this->listenersCallback[$key] );
                }
            }
        // standard listeners
        } else {

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
        }
        return $this;
    }

    public function removeAllListeners( $event = null )
    {
        if( $event === null ) {
            $this->listeners = [];
            $this->listenersOnce = [];
            $this->listenersCallback = [];
        } else {
            if( $event instanceof \Closure ) {
                foreach( $this->listenersCallback as $key => $value ) {
                    if( $value[0] === $event ) {
                        unset( $this->listenersCallback[$key] );
                    }
                }
            } else {
                unset( $this->listeners[$event] );
                unset( $this->listenersOnce[$event] );
            }
        }
        return $this;
    }

    public function listeners( $event )
    {
        if( $event instanceof \Closure) {
            $output = [];
            foreach( $this->listenersCallback as $value ) {
                if( $value[0] === $event ) {
                    $output[] = $value[1];
                }
            }
            return $output;
        } elseif( isset( $this->listeners[$event], $this->listenersOnce[$event] ) ) {
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

        // iterate over our listenercallbacks and see if we've got anything to test against
        if( $this->listenersCallback ) {
            // it's a mild hack but use 'Event' to manage the dispatch of new callback this gives a whole load of syntatic sugar for free and presents a common interface
            foreach( $this->listenersCallback as $value ) {
                if( $event->dispatch($value[0], 0) ) {
                    $event->dispatch( $value[1] );
                }
            }
        }

        if( $this->eventDebug ) {
            d_pr(
                sprintf(
                    "%s %s %s",
                    $event->name,
                    $event->dispatchCount,
                    json_encode( $event->dispatchArgs )
                )
            );
        }

        $this->tick();

        return $event;
    }

    public function removeTimeout( $index )
    {
        unset($this->timeouts[$index]);
        return $this;
    }

    public function setTimeout( $callback, $duration )
    {
        $this->timeouts[] = [microtime(true) + $duration, $callback];
        end($this->timeouts);
        return key($this->timeouts);
    }

    public function removeInterval( $index )
    {
        unset($this->intervals[$index]);
        return $this;
    }

    public function setInterval( $callback, $frequency )
    {
        $this->intervals[] = [microtime(true) + $frequency, $callback, $frequency];
        end($this->intervals);
        return key($this->intervals);
    }

    public function tick()
    {
        $currentTime = microtime(true);
        foreach( $this->timeouts as $index => $info ) {
            if( $info[0] <= $currentTime ) {
                $event = new Event( 'timeout', [], $this );
                $event->dispatch( $info[1] );
                // remove from the queue
                unset( $this->timeouts[$index] );
            }
        };
        foreach( $this->intervals as &$info ) {
            if( $info[0] <= microtime(true) ) {
                $event = new Event( 'interval', [], $this );
                $event->dispatch( $info[1] );
                $info[0] = microtime(true) + $info[2];
            }
        }
        return $this;
    }

    public function timeouts()
    {
        return $this->timeouts;
    }

    public function intervals()
    {
        return $this->intervals;
    }

}