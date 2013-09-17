<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

class BackTrace
{

    private $trace;

    /**
     * null => calls debug_backtrace
     * Exception => ->getTrace()
     * @param mixed \Exception or NULL
     */
    public function __construct( $exceptionOrNull = null )
    {
        if( $exceptionOrNull instanceof \Exception ) {
            $this->trace = $exceptionOrNull->getTrace();
        } else {
            $this->trace = debug_backtrace();
        }
    }

    /**
     * Get the string representation of this backtrace
     * @return string
     */
    public function __toString()
    {

        try {
            $trace = $this->getSane(0);
            $output = [];

            foreach( $trace as $n => $fragment ) {
                $output[] = $this->toStringTraceComponent( $n, $fragment );
            }
            return implode( "\n", $output );
        } catch ( \Exception $e ) {
            d_e( $e );
            die();
        }

    }

    private function toStringTraceComponent( $n, array $component )
    {

        $fn = isset( $component['class'] ) ?
            "{$component['class']}{$component['type']}" :
            '';
        $fn .= $component['function'];

        return sprintf(
            "#%d %s(%d): %s(%s)",
            $n,
            $component['file'],
            $component['line'],
            $fn,
            implode( ", ", $component['args'] )
        );

    }

    /**
     * Shift trace, filter as required and fix the arguments object
     * @return array
     */
    public function getSane( $shift = 1, $limitOrFilter = null )
    {

        $backtrace = $this->trace;

        while( $shift-- > 0  ) {
            array_shift( $backtrace );
        }

        if( is_numeric( $limitOrFilter ) ){
            $backtrace = array_slice( $backtrace, 0, (int) $limitOrFilter );
            $limitOrFilter = null;
        }

        $formattedTrace = array_map(
            array( $this, 'traceComponentMakeSane' ),
            $backtrace
        );

        // Apply the filter. Trace fragment is returned if the string limitOrFilter is found anywhere in a trace
        if( null !== $limitOrFilter ) {
            foreach( $formattedTrace as $key => $trace ) {
                $traceMatchesFilter = false;
                while( !$traceMatchesFilter and list(,$traceLine) = each($trace) ) {
                    if( is_string( $traceLine ) ) {
                        $traceMatchesFilter = false !== stripos( $traceLine, $limitOrFilter );
                    }
                }
                if( !$traceMatchesFilter ) {
                    unset( $formattedTrace[$key] );
                }
            }
        }

        return $formattedTrace;

    }

    /**
     * Format a trace into a more managable output when massive objects are involved
     * @param array $trace
     * @return array
     */
    private function traceComponentMakeSane( $trace )
    {
        // debugging wondering why some traces's don't have a args object set
        if( isset( $trace['args'] ) ) {
            $trace['args'] = array_map(
                function($arg){
                    switch(true) {
                        case is_object($arg):
                            return get_class($arg);
                        case is_bool($arg):
                            return $arg ? 'true' : 'false';
                        case is_scalar($arg):
                            return $arg;
                        default:
                            return gettype($arg);
                    }
                },
                $trace['args']
            );
        } else {
            $trace['args'] = array();
        }
        if( isset( $trace['file'] ) and false !== $pos = strpos( $trace['file'], '/Symfony/' ) ) {
            $trace['file'] = ".".substr( $trace['file'], $pos );
        }

        // set the file and line properties if they don't exist
        if( !array_key_exists( 'file', $trace ) ) {
            $trace['file'] =  null;
        }
        if( !array_key_exists( 'line', $trace ) ) {
            $trace['line'] =  null;
        }

        // attempt to add file and line back in
        // not sure why this would be required - it seems very hacky but seemingly corrects some inexplicatbly missing information
        if( !$trace['file'] and !$trace['line'] and isset( $trace['class'] ) and isset( $trace['function'] ) ) {
            try {
                $reflMethod = new \ReflectionMethod( $trace['class'], $trace['function'] );
                $trace['file'] = $reflMethod->getFileName();
                $trace['line'] = $reflMethod->getStartLine();
            } catch ( \Exception $e ) {
            }
        }

        // unset the object property - not sure why this has appeared. It wasn't present in the output of the original sane_debug_backtrace()
        unset( $trace['object'] );
        return $trace;
    }

}