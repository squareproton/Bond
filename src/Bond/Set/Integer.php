<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Set;

use Bond\Set;

class Integer extends Set
{

    /**
     * The postgres datatype values should be cast to
     */
    const CAST = 'INTEGER';

    /**
     * Class constructor
     * @param mixed $data
     */
    public function __construct( $data = null )
    {

        $args = func_get_args();

        // super quick setup the set without all the additional checks.
        // short circu
        if( count( $args ) === 1 ) {

            // array with one value
            if( is_array($data) and count($data) === 1 ) {
                $data = array_pop( $data );
            }

            // is int or // is intish and positive, '-1' as a string isn't a valid int, it's a range
            if( is_int( $data ) or ( is_scalar( $data ) and (string) $data === (string) abs($data) ) ) {

                $this->intervals = array(
                    array( (int) $data, (int) $data ),
                );
                return;

            }
        }

        // call parent constructor
        call_user_func_array( 'parent::__construct', $args );

    }

    /**
     * If passed an array of ints we don't need to a whole slew of other testing
     * # THIS FUNCTION IS IN HERE FOR PERFORMACE REASONS ONLY
     * @param mixed $data
     * @return array
     */
    protected function addHelper( $data )
    {

        // if this is a array of real integers we can short circuit further testing
        if( is_array( $data ) ) {

            // are we an array of ints
            reset( $data );
            $isInt = true;
            while( $isInt && list(,$value) = each($data) ) {
                $isInt = ( $isInt and is_int( $value ) );
            }

            if( $isInt ) {

                // sort integers
                // means the slower, null safe, interval function has less work to do
                sort( $data, SORT_NUMERIC );

                return array_map( null, $data, $data );
            }

        }

        return parent::addHelper( $data );

    }

    /**
     * Cast a value to the correct type
     * @param mixed $value mixed Value we're casting.
     * @param string String representation of any cating errors
     * @return bool Is this value valid castable to the correct type
     */
    protected function cast( &$value, &$error )
    {

        if( $this->process & self::PROCESS_TRIM ) {
            $value = trim( (string) $value );
        }
        if( $this->process & self::PROCESS_STRIP_BADCHARS and strlen( $value ) > 0 ) {
            $value = preg_replace('/[^0-9\\-\\.]/', '', $value);
            if( strlen( $value) === 0 ) {
                return false;
            }
        }

        $value = $value === '' ? null : (int) $value;
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function isContinuous( $lower, $upper )
    {
        return ++$lower === $upper;
    }

    /**
     * {@inheritDoc}
     */
    protected function nextHighestValue( $value )
    {
        return $value + 1;
    }

    /**
     * {@inheritDoc}
     */
    protected function nextLowestValue( $value )
    {
        return $value - 1;
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        $output = parent::count();
        foreach( $this->intervals as $interval ) {
            if( $interval[0] === null or $interval[1] === null ) {
                return null;
            } else {
                $output += ( $interval[1] - $interval[0] ) + 1; # add the 1 because the interval is inclusive
            }
        }
        return $output;
    }

    /**
     * {@inheritDoc}
     */
    public function isNumeric()
    {
        return true;
    }

}