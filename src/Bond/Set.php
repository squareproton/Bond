<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Sql\Query;
use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

abstract class Set implements \Countable, SqlInterface
{

    const INTERVAL_SEPARATOR = ',';
    const RANGE_SEPARATOR = '-';
    const ESCAPE_CHARACTER = "\\";
    const NULL_CHARACTER = "0";

    const PROCESS_STRIP_BADCHARS = 1;
    const PROCESS_UPPERCASE = 2;
    const PROCESS_TRIM = 4;

    const NULL_IS_LOW = -1;
    const NULL_IS_HIGH = 1;

    /**
     * Interval store. These are minimal and ordered.
     * @var mixed
     */
    protected $intervals = array();

    /**
     * The modifiers we're going to apply to the interval as it is created
     * See the class constants process
     */
    protected $process = 5;

    /**
     * Sql Indentifier. Used if this Set is ever cast to SQL. Optional
     * @var string
     */
    protected $sqlIdentifier;

    /**
     * Contains nUll
     */
    protected $containsNull = false;

    /**
     * Class constructor
     * See $this->add.
     */
    public function __construct()
    {
        call_user_func_array(
            array($this, 'add'),
            func_get_args()
        );
    }

    /**
     * Variable number of argument of things we're likey to be able to make a set out of.
     *
     * @param See $this->addHelper()
     * ..
     * @return $this
     */
    public function add()
    {

        if( !$args = func_get_args() ) {
            return $this;
        }

        $intervals = array_map(
            array($this, 'addHelper'),
            $args
        );
        $intervals[] = $this->intervals;

        $output = array();

        // merge the lot
        $intervals = call_user_func_array( 'array_merge', $intervals );

        usort( $intervals, array( $this, "sortIntervals" ) );

        $working = null;
        while( list(,$current) = each($intervals) ) {

            // we got anything to compare against
            if( $working === null ) {

                $working = $current;

            // we've maxed out - everything else is going to match this!
            } elseif ( $working[1] === null ) {

                break;

            // is current lower bound contained in our working interval
            // or isContinuous()
            } elseif (
                $this->sort( $working[1], $current[0], self::NULL_IS_HIGH ) >= 0 or
                $this->isContinuous( $working[1], $current[0] )
            ) {

                // get the maximum upper bound
                $working[1] = $this->max( $working[1], $current[1], self::NULL_IS_HIGH );

            // intervals are distinct, add to output
            } else {

                $output[] = $working;
                $working = $current;

            }

        }

        if( !is_null( $working ) ) {
            $output[] = $working;
        }

        $this->intervals = $output;
        return $this;

    }

    /**
     * Remove a stuff from set.
     * Takes the usual arguments see __construct or add.
     * @return $this
     */
    public function remove()
    {

        $reflection = new \ReflectionClass( get_called_class() );
        $removing = $reflection->newInstanceArgs( func_get_args() );

        reset( $this->intervals );
        reset( $removing->intervals );

        list(,$remove) = each($removing->intervals);
        list(,$current) = each($this->intervals);

        $output = array();

        $c = 0;
        while( $remove and $current and $c++ < 100 ) {

            // Intervals don't yet overlap in any way. Current lags remove.
            //       |---|  // remove
            // |---|        // current
            if( $remove[0] !== null and $this->sort( $remove[0], $current[1], self::NULL_IS_HIGH ) > 0 ) {

                $output[] = $current;
                list(,$current) = each($this->intervals);
                continue;

            }

            // Intervals don't yet overlap in any way. Remove lags current.
            //  |---|       // remove
            //        |---| // current
            if( $current[0] !== null and $this->sort( $current[0], $remove[1], self::NULL_IS_HIGH ) > 0 ) {
                list(,$remove) = each($removing->intervals);
                continue;
            }

            // lower check
            $lowerBoundOverlap = $this->sort( $remove[0], $current[0], self::NULL_IS_LOW );

            // upper check
            $upperBoundOverlap = $this->sort( $current[1], $remove[1], self::NULL_IS_HIGH );

            // is current completely contained in remove
            if( $lowerBoundOverlap <= 0 and $upperBoundOverlap <= 0 ) {
                list(,$current) = each($this->intervals);
                continue;
            }

//            print_r_pre( ":0:\n", $current, $remove );

            // remove is completely contained in current, we're splitting into two halves
            if( $lowerBoundOverlap === 1 and $upperBoundOverlap === 1 ) {

                $remove = array(
                    $this->nextLowestValue( $remove[0] ),
                    $this->nextHighestValue( $remove[1] )
                );

                if( $remove[0] !== $remove[1] ) {

                    // lower interval
                    $output[] = array(
                        $current[0],
                        $remove[0]
                    );

                    $current[0] = $remove[1];

                }

                list(,$remove) = each($removing->intervals);

//                print_r_pre( ":1:\n", $current, $remove );

            // remove's upper bound is <= current upperbound and lower bound is completely covered
            } elseif( $upperBoundOverlap === 1 ) {

                $current[0] = $this->nextHighestValue( $remove[1] );

                list(,$remove) = each($removing->intervals);

//                print_r_pre( ":2:\n", $current, $remove );

            // remove's upperbound is >= current upperbound and lower bound is free
            } elseif( $lowerBoundOverlap === 1 ) {

                // lower interval
                $output[] = array(
                    $current[0],
                    $this->nextLowestValue( $remove[0] )
                );
                list(,$current) = each($this->intervals);

//                print_r_pre( ":3:\n", $current, $remove );

            // this shouldn't happen
            } else {

                throw new \RuntimeException("this state hasn't been accounted for - this should never happen");

            }

        }

        if( $current ) {
            $output[] = $current;
        }

        // add in any remaining intervals not yet added
        while( list(,$current) = each($this->intervals) ) {
            $output[] = $current;
        }

        $this->intervals = $output;
        return $this;

    }

    /**
     * Is a set contained within another set
     * @return <type>
     */
    public function contains()
    {

        // build new set from passed arguments
        $reflection = new \ReflectionClass( get_called_class() );
        $comparing = $reflection->newInstanceArgs( func_get_args() );

        // we don't have a null but the set we're comparing against has it
        if( !$this->containsNull and $comparing->containsNull ) {
            return false;
        }

        reset( $this->intervals );
        reset( $comparing->intervals );

        list(,$compare) = each($comparing->intervals);
        list(,$current) = each($this->intervals);

        while( $compare and $current) {

            // Intervals are maximal. They're either entirely contained or not at all
            if( $compare[0] !== null and $this->sort( $compare[0], $current[1], self::NULL_IS_HIGH ) > 0 ) {
                list(,$current) = each($this->intervals);
                continue;
            }

            // are intervals contained?
            if(
                // compare lower is >= current lower
                $this->sort( $compare[0], $current[0], self::NULL_IS_LOW ) >= 0 and
                // compare upper is <= current upper
                $this->sort( $compare[1], $current[1], self::NULL_IS_HIGH ) <= 0
            ) {

                list(,$compare) = each($comparing->intervals);

            // interval can't be contained
            } else {

                return false;

            }

        }

        // if we're still got something to comparing something we've not been able to match it up with a interval
        return !$compare;

    }

    /**
     * Invert a set.
     * @return $this;
     */
    public function invert()
    {

        $this->containsNull = !$this->containsNull;

        if( $this->intervals === array( array(null, null) ) ) {
            $this->intervals = array();
            return $this;
        }

        $output = array();
        $working = array( null, null );

        reset( $this->intervals );

        while( list(,$interval) = each($this->intervals) ) {

            if( $interval[0] === null ) {

                $working[0] = $this->nextHighestValue( $interval[1] );

            } elseif( $interval[1] === null ) {

                $working[1] = $this->nextLowestValue( $interval[0] );
                break;

            } else {

                $working[1] = $this->nextLowestValue( $interval[0] );
                $output[] = $working;

                $working = array(
                    $this->nextHighestValue( $interval[1] ),
                    null
                );

            }

        }

        $output[] = $working;
        $this->intervals = $output;
        return $this;

    }

    /**
     * Make this set the 'match-all' set
     * @return $this
     */
    public function all()
    {
        $this->intervals = array(
            array( null, null )
        );
        $this->containsNull = true;
        return $this;
    }

    /**
     * Make this set match nothing
     * @return $this
     */
    public function none()
    {
        $this->intervals = array();
        $this->containsNull = false;
        return $this;
    }

    /**
     * Convert something that looks like it's going to be a set castable into a array of intervals
     * Not nessisarily sorted
     * @param mixed $data
     * @return array Intervals
     */
    protected function addHelper( $data )
    {

        // array of values
        if( is_array( $data ) ) {

            $originalSize = count( $data );
            $data = array_filter( $data, '\Bond\is_not_null' );

            // have null
            if( $originalSize !== count( $data ) ) {
                $this->containsNull = true;
            }

            $argIntervals = array_map( null, $data, $data );
            $argIntervals = array_map( array($this, 'prepInterval'), $argIntervals );

            return array_filter( $argIntervals );

        } elseif( $data instanceof static ) {

            return $data->intervals;

        } elseif( $data === null ) {

            $this->containsNull = true;
            return array();

        } elseif( is_int($data) ) {

            return $this->parseStringToIntervals( $this->escape( $data ) );

        } elseif( is_scalar( $data ) ) {

            return $this->parseStringToIntervals( (string) $data );

        }

        throw new \RuntimeException( "Don't know how to handle this `{$data}`." );

    }

    /**
     * Starting point for the specilist count functions
     * @return int|null
     */
    public function count()
    {
        return $this->containsNull ? 1 : 0;
    }

    /**
     * Is this set empty?
     * @return bool
     */
    public function isEmpty()
    {
        return count( $this->intervals ) === 0;
    }

    /**
     * Is this Set numeric
     * @return bool
     */
    public function isNumeric()
    {
        return false;
    }

    /**
     * Convert our intervals into a rigorous (min max) two valued array
     * @param array $interval
     * @return array|null
     */
    private final function prepInterval( array $interval, &$error = null )
    {

        $error = null;

        if( $interval === array('') ) {
            return null;
        }

        if( $interval === array(null) ) {
            $this->containsNull = true;
            return null;
        }

        // cast interval to correct type
        foreach( $interval as &$value ) {
            if( !$this->cast( $value, $error ) ) {
                return null;
            }
        }

        switch( count( $interval ) ) {

            case 1:
                $interval[1] = $interval[0];
                break;

            case 2:

                if( $interval[0] !== null and $interval[1] !== null ) {
                    usort( $interval, array( $this, 'sort' ) );
                }
                break;

            default:
                $error = "too many fragments";
                return null;

        }

        return $interval;

    }

    /**
     * Parse the data into an array of unique values
     * @param string $string String represensation of a set which we're going to parse into
     * a valid, sorted array of intervals
     * @return array
     */
    private function parseStringToIntervals( $string )
    {

        if( !$length = mb_strlen( $string ) ) {
            return array();
        }

        // The following isn't as bat shit crazy loco nutjob wtf loony as it looks.
        // People need to enter literal commas, dashes and backslashes and you can't easily write a regex for this because the escape sequences can become, well arbritraily complex.
        // Imagine the following
        //      200-300 => array( 200, 300 ); great
        //      \\200-300 => array( \200, 300 ); fine, you can do a regex for that
        //      200\\\-300 => array( 200\-300 ); but not easily for this or something more insane-o
        // Unfortunately php's str_csv functions fails us here with a enclosure delimiter as the empty string ''. This means the DELIMTER (or INTERVAL_SEPARATOR) isn't escaped properly.

        // The following works fine for non-multibyte char separators and is very quick.
        $intervals = array();
        $interval = array('');
        $c = 0;

        $isCharEscaped = false;

        for( $i = 0; $i < $length; $i++ ) {

            $char = mb_substr( $string, $i, 1 );

            if( $isCharEscaped ) {

                if( $char === self::NULL_CHARACTER ) {
                    if( $interval[$c] === '') {
                        $interval[$c] = null;
                    }
                } else {
                    $interval[$c] .= $char;
                }

                $isCharEscaped = false;

            } else {

                switch( $char ) {
                    case self::ESCAPE_CHARACTER;
                        $isCharEscaped = true;
                        break;
                    case self::RANGE_SEPARATOR;
                        $interval[++$c] = '';
                        break;
                    case self::INTERVAL_SEPARATOR;
                        if( $interval = $this->prepInterval( $interval ) ) {
                            $intervals[] = $interval;
                        }
                        $interval = array('');
                        $c = 0;
                        break;
                    default:
                        $interval[$c] .= $char;
                        break;

                }

            }

        }

        if( $interval = $this->prepInterval( $interval ) ) {
            $intervals[] = $interval;
        }

        return $intervals;

    }

    /**
     * Are to values adjacent. That is to say can their intervals be safely merged.
     * Eg,
     *     The integers 1,2 are continuous because there doesn't exists a integer between 1 and 2.
     *     The real numbers 1.5 and 1.6 are not continuous.
     *     Not figured out how this should work with respect to text strings. Difficult problem. Are PARTNO123 and PARTNO124 continuous. What about PARTNO123a? Humm.
     *
     * @param mixed $lower
     * @param mixed $upper
     * @return return bool
     */
    protected function isContinuous( $lower, $upper )
    {
        return false;
    }

    /**
     * Get the next highest value in our ordering. All sets have a natural ordering, integers, 1,2,3,4... string, A,B,C,D.
     * If the set is not continuous there is a 'next highest value'. If continous return $value
     * @param mixed $value
     * @return mixed $value
     */
    protected function nextHighestValue( $value )
    {
        throw new \BadMethodCallException("overload required");
    }

    /**
     * See, nextHighestValue()
     * @param mixed $value
     * @return mixed $value
     */
    protected function nextLowestValue( $value )
    {
        throw new \BadMethodCallException("overload required");
    }

    /**
     * Sort two intervals
     * @param array $a
     * @param array $b
     * @return -1,0,1
     */
    private final function sortIntervals( $a, $b )
    {
        if( 0 !== $lowerCompare = $this->sort( $a[0], $b[0], self::NULL_IS_LOW ) ) {
            return $lowerCompare;
        }
        return -$this->sort( $a[1], $b[1], self::NULL_IS_HIGH );
    }

    /**
     * Sort a two intervals. Similar structure to a standard php/javascript custom sort function. Returns 1 if $a > $b.
     * @param mixed $a
     * @param mixed $b
     * @param mixed $handleNull Is null treated as a high value or a low value. See, self::NULL_IS_HIGH
     * @return -1,0,1
     */
    protected function sort( $a, $b, $handleNull = self::NULL_IS_LOW )
    {
        if( $a === $b ) {
            return 0;
        } elseif( $a === null ) {
            return $handleNull === self::NULL_IS_LOW ? -1 : 1;
        } elseif( $b === null ) {
            return $handleNull === self::NULL_IS_LOW ? 1 : -1;
        }
        return $a > $b ? 1 : -1;
    }

    /**
     * Return the max of two things
     * @param mixed $a
     * @param mixed $b
     * @param mixed $handleNull Is null treated as a high value or a low value. See, self::NULL_IS_HIGH
     * @return mixed
     */
    protected function max( $a, $b, $handleNull )
    {
        return $this->sort( $a, $b, $handleNull ) > 0 ? $a : $b;
    }

    /**
     * Return the min of two things.
     * @param mixed $a
     * @param mixed $b
     * @param mixed $handleNull Is null treated as a high value or a low value. See, self::NULL_IS_HIGH
     * @return mixed
     */
    protected function min( $a, $b, $handleNull )
    {
        return $this->sort( $a, $b, $handleNull ) > 0 ? $b : $a;
    }

    /**
     * Convert values set into string
     * @returns string. String representation of this ValuesSet
     */
    public function __toString()
    {

        $output = array();

        if( $this->containsNull ) {
            $output[] = $this->escape( null );
        }

        foreach( $this->intervals as $interval ) {

            if( $interval[0] === $interval[1] and $interval[0] !== null ) {
                $output[] = $this->escape( $interval[0] );
            } else {
                $output[] = sprintf(
                    "%s-%s",
                    $this->escape( $interval[0], false ),
                    $this->escape( $interval[1], false )
                );
            }

        }

        return implode( self::INTERVAL_SEPARATOR, $output );

    }

    /**
     * Escape a interval fragments
     * @param scalar $value
     * @return string
     */
    public static function escape( $value, $escapeNull = true )
    {

        if( $value === null ) {
            return $escapeNull ? self::ESCAPE_CHARACTER . self::NULL_CHARACTER : '';
        }

        $value = str_replace( self::ESCAPE_CHARACTER, self::ESCAPE_CHARACTER . self::ESCAPE_CHARACTER, $value );
        $value = str_replace( self::RANGE_SEPARATOR, self::ESCAPE_CHARACTER . self::RANGE_SEPARATOR, $value );
        $value = str_replace( self::INTERVAL_SEPARATOR, self::ESCAPE_CHARACTER . self::INTERVAL_SEPARATOR, $value );

        return $value;

    }

    /**
     * Standard getter
     * @param mixed $key
     */
    public function __get( $key )
    {
        switch( $key ) {
            case 'intervals':
            case 'sqlIdentifier':
                return $this->$key;
        }
        throw new \InvalidArgumentException( "Unknown key {$key}" );
    }

    /**
     * Standard setter. Blah, blah.
     * @param mixed $key
     * @param mixed $value
     */
    public function __set( $key, $value ) {
        switch( $key ) {
            case 'sqlIdentifier':
                $this->sqlIdentifier = (string) $value;
                return;
        }
        throw new \InvalidArgumentException( "Set set property {$key}" );
    }

    /**
     * This is a duplication of the functionality found in __set() because I want chaining.
     * It's very useful to be able to say, in a sprintf,  $set->sqlIdentifierSet( 'something' ) and have it still work
     * @param mixed $value
     * @return $this
     */
    public function sqlIdentifierSet( $value )
    {
        $this->__set( 'sqlIdentifier', $value );
        return $this;
    }

    /** SQL Interface **/

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoting )
    {

        if( !isset( $this->sqlIdentifier ) ) {
            throw new \RuntimeException("You can't use a Bond\Set in a Query object without first setting its \$this->sqlIdentifier property.");
        }

        $numIntervals = count( $this->intervals );
        $currentInterval = 0;
        $sqlFragments = array();

        // the full range
        if( $numIntervals === 1 and $this->intervals[0] === array( null, null ) ) {

            // with null, this matches everything
            if( $this->containsNull ) {

                return "TRUE";

            } else {

                // just not null values
                $sqlFragments[] = sprintf(
                    "%s IS NOT NULL",
                    $this->sqlIdentifier
                );
                $currentInterval++;

            }

        // we've potentially got range(s)
        } elseif( $numIntervals ) {

            // We'll only ever have one < test and one > test. Lets handle those.
            if( $this->intervals[0][0] === null ) {

                $currentInterval++;

                $sqlFragments[] = sprintf(
                    "%s <= %s",
                    $this->sqlIdentifier,
                    $quoting->quote( $this->intervals[0][1] )
                );

            }

            if( $this->intervals[$numIntervals-1][1] === null ) {

                $sqlFragments[] .= sprintf(
                    "%s >= %s",
                    $this->sqlIdentifier,
                    $quoting->quote( $this->intervals[$numIntervals-1][0] )
                );

                $numIntervals--;

            }

        }

        // all these will be BETWEEN statements or IN values
        $sqlValues = array();
        for( ; $currentInterval < $numIntervals; $currentInterval++ ) {

            // in values
            if( $this->intervals[$currentInterval][0] === $this->intervals[$currentInterval][1] ) {

                $sqlValues[] = $quoting->quote( $this->intervals[$currentInterval][0] );

            // between
            } else {

                $sqlFragments[] = sprintf(
                    "%s BETWEEN %s AND %s",
                    $this->sqlIdentifier,
                    $quoting->quote( $this->intervals[$currentInterval][0] ),
                    $quoting->quote( $this->intervals[$currentInterval][1] )
                );

            }

        }

        // NULL
        if( $this->containsNull ) {
            $sqlFragments[] = sprintf(
                "%s IS NULL",
                $this->sqlIdentifier
            );
        }

        // turn any sql values into a IN statement
        if( $sqlValues ) {

            $sqlFragments[] = sprintf(
                "%s IN (%s)",
                $this->sqlIdentifier,
                implode( ',', $sqlValues )
            );

        }

        return count( $sqlFragments )
              // combine all values into one unified sql statement
            ? "( ". implode( ' OR ', $sqlFragments ) . " )"
              // nothing to match
            : "FALSE"
            ;

    }

}