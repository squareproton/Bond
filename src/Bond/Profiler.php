<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

/**
 * Simple class to help you debug and find performance problems
 */
class Profiler
{

    private static $instance;
    private $name;
    private $log = array();

    public function __construct( $name = null )
    {
        $this->name = $name;
    }

    public static function init()
    {
        if( !self::$instance ) {
            self::$instance = new static('SINGLETON');
        }
        return self::$instance;
    }

    public function log( $name = null, $n = null )
    {
        $this->log[] = array(
            'name' => $name,
            'time' => microtime( true ),
            'n' => $n,
        );
        return $this;
    }

    public function __toString()
    {
        return $this->output();
    }

    public function output()
    {
        return \Bond\is_command_line()
            ? $this->formatForCommandLine()
            : $this->formatForHTML()
            ;
    }

    public function formatForHTML()
    {
        return "<pre>".$this->formatForCommandLine()."</pre>";
    }

    public function formatForCommandLine()
    {

        if( !$this->log ) {
            return "nothing logged\n";
        }
        $start = $this->log[0]['time'];

        // column widths
        $output = '';
        $width = array(
            '#' => 1,
            'name' => 4,
            'dt' => 2,
            'n' => 1,
            'cumulative' => 10,
            'time' => 4,
//            'trace' => 8,
        );

        $header = array_combine(
            $keys = array_keys( $width ),
            array_map( 'ucwords', $keys )
        );
        $log = array();
        $dt = null;

        foreach( $this->log as $n => $line ) {

            $line = array( '#' => $n ) + $line;
            $line['cumulative'] = $this->formatTime( $line['time'] - $start );
            $line['dt'] = isset( $dt ) ? $this->formatTime( $line['time'] - $dt ) : '-';

            $line['n'] = isset( $line['n'] ) ? $this->formatTime( ( $line['time'] - $start ) / $line['n'] ) : '-';

            if( isset( $line['trace'] ) and isset( $width['trace']) ) {
                $line['trace'] = $line['trace'][0]['file'] . ":" . $line['trace'][0]['line'];
            }

            $log[] = $line;
            $dt = $line['time'];

            // determine max widths
            foreach( $width as $key => $value ) {
                if( isset( $line[$key] ) ) {
                    $width[$key] = max( strlen($line[$key]), $width[$key] );
                }
            }

        }

        // got name?
        if( $this->name ) {
            $output .= "\n {$this->name}";
        }

        // main body
        $output .= "\n". $this->formatLogLine( $header, $width );
        foreach( $log as $line ) {
            $output .= $this->formatLogLine( $line, $width );
        }

        return $output;

    }

    private function formatLogLine( $log, array $width )
    {
        $output = '';
        foreach( $width as $key => $width ) {
            $output .= $this->stringPad( $log[$key], $width + 1 );
        }
        return $output . "\n";
    }

    public static function stringPad( $string, $length )
    {
        return str_pad(
            $string,
            abs($length) + strlen($string) - mb_strlen($string),
            ' ',
            $length < 0 ? STR_PAD_LEFT : STR_PAD_RIGHT
        );
    }

    public static function formatTime( $seconds )
    {

        if( null === $seconds ) {
            return null;
        }

        // > 1min - 1min 45s
        if( $seconds > 604800 ) {

            $hours = floor( $seconds / 3600 );
            $mins = ( $seconds - ( $hours * 3600 ) ) / 60;
            return sprintf( "%dhours %ds", $hours, round( $mins ) );

        } elseif( $seconds > 60 ) {

            $mins = floor( $seconds / 60 );
            $min_seconds = $seconds - ( $mins * 60 );
            return sprintf( "%dmin %ds", $mins, $min_seconds );

        // 10 sec -> 45.4s
        } elseif( $seconds > 10 ) {

            return sprintf( "%.1fs", $seconds );

        // 2.5 sec -> 7.54s
        } elseif( $seconds > 2.5 ) {

            return sprintf( "%.2fs", $seconds );

        // 0.1 sec > 1456ms
        } elseif( $seconds > 0.1 ) {

            return sprintf( "%dms", $seconds * 1000 );

        // 0.01 sec > 245.6ms
        } elseif( $seconds > 0.01 ) {

            return sprintf( "%0.1fms", $seconds * 1000 );

        // 0.001 sec > 4.67ms
        } elseif( $seconds > 0.001 ) {

            return sprintf( "%0.1fms", $seconds * 1000 );

        // 0.0001 sec > 1004.67ms
        } else {

            return sprintf( "%dµs", $seconds * 1000 * 1000 );

        }

    }

}
