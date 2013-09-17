<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Set;

use Bond\Set;

class Text extends Set
{

    /**
     * The postgres datatype values should be cast to
     */
    const CAST = 'TEXT';

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
        if( $this->process & self::PROCESS_UPPERCASE ) {
            $value = strtoupper( $value );
        }
        $value = (string) $value;
        if( $value === '' ) {
            $value = null;
        }
        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function isContinuous( $lower, $upper )
    {
        return false;;
    }

    /**
     * {@inheritDoc}
     */
    protected function nextHighestValue( $value )
    {
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    protected function nextLowestValue( $value )
    {
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function count()
    {
        $output = parent::count();
        foreach( $this->intervals as $interval ) {
            if(
                ( $interval[0] === null or $interval[1] === null ) or
                ( $interval[0] !== $interval[1] )
            ) {
                return null;
            } else {
                $output++;
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

    /**
     * {@inheritDoc}
     */
    public function invert()
    {
        // we can't invert a text set if it has any 'values' (ie, non range intervals) in it
        // check for these and throw a error if we have any
        foreach( $this->intervals as $interval ) {
            if( $interval[0] === $interval[1] and $interval[0] !== null ) {
                throw new \RuntimeException("I'm sorry I can't invert the text set('{$this}') because it contains values.");
            }
        }
        return parent::invert();
    }

}