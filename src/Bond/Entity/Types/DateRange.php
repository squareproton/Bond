<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types;

use Bond\Entity\Types\DateInterval;
use Bond\Entity\Types\DateTime;

use Bond\Exception\BadDateTimeException;
use Bond\Exception\BadTypeException;
use Bond\Exception\NotImplementedYet;

use Bond\MagicGetter;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

use Serializable;

/**
 * @author Pete
 */
class DateRange implements SqlInterface, Serializable
{

    use MagicGetter;

    const LOWER_CONTAIN = 1;
    const LOWER_CONTAIN_NOT = 2;
    const LOWER_CONTAIN_DEFAULT = 1;

    const UPPER_CONTAIN = 4;
    const UPPER_CONTAIN_NOT = 8;
    const UPPER_CONTAIN_DEFAULT = 8;

    private $lower;
    private $upper;
    private $bounds = 0;

    /**
     * Add ability to instantiate this interval with a array
     * @param mixed $interval
     */
    public function __construct( DateTime $lower, DateTime $upper, $bounds = 0 )
    {

        $bounds += $bounds & ( self::LOWER_CONTAIN | self::LOWER_CONTAIN_NOT ) ? 0 : self::LOWER_CONTAIN_DEFAULT;
        $bounds += $bounds & ( self::UPPER_CONTAIN | self::UPPER_CONTAIN_NOT ) ? 0 : self::UPPER_CONTAIN_DEFAULT;

        $this->lower = $lower;
        $this->upper = $upper;
        $this->bounds = $bounds;
    }

    /**
     * Static entry point to generate a date rnage object from a string representation of a tsrange
     * @return Bond\Entity\Type\DateRange
     */
    public static function makeFromString( $range )
    {

        if( !is_string($range) ) {
            throw new BadTypeException( $range, 'string' );
        }
        if( !preg_match(
                '/^
                    (\\(|\\[)
                    (["\']?)(.*)?\2
                    ,
                    (["\']?)(.*)?\4
                    (\\)|\\])
                $/x',
                $range,
                $matches
            )
        ) {
            throw new BadTypeException( $range, 'DateTime string representation');
        }

        $lower = new DateTime($matches[3]);
        $upper = new DateTime($matches[5]);
        $bounds  = ( $matches[1] === '[' ? self::LOWER_CONTAIN : self::LOWER_CONTAIN_NOT );
        $bounds += ( $matches[6] === ']' ? self::UPPER_CONTAIN : self::UPPER_CONTAIN_NOT );

        // inistante object without going via constructer to allow us to save on validation
        $refl = new \ReflectionClass( __CLASS__ );
        $obj = $refl->newInstanceWithoutConstructor();
        $obj->lower = $lower;
        $obj->upper = $upper;
        $obj->bounds = $bounds;
        return $obj;
    }

    /**
     * Serialization interface
     * @return string
     */
    public function serialize()
    {
        return json_encode([
            serialize( $this->lower ),
            serialize( $this->upper ),
            $this->bounds
        ]);
    }

    /**
     * Unserialization interface
     * @return void
     */
    public function unserialize($data)
    {
        $data = json_decode($data);
        $this->lower = unserialize( $data[0] );
        $this->upper = unserialize( $data[1] );
        $this->bounds = $data[2];
    }

    /**
     * Does this interval contain our time
     * @return Bond\Entity\T
     */
    public function contains( DateTime $datetime )
    {

        $lower = $this->lower->toUnixTimestamp();
        $upper = $this->upper->toUnixTimestamp();
        $working = $datetime->toUnixTimestamp();

        $lowerCompare = bccomp( $lower, $working );
        $upperCompare = bccomp( $upper, $working );

//        echo "\n{$lower} {$upper} {$working} {$lowerCompare} {$upperCompare}";

        // fucking hell, this was much more annoying than it should be
        if( $lowerCompare === 1 || ( $this->bounds & self::LOWER_CONTAIN_NOT and $lowerCompare === 0 ) ) {
            return false;
        }

        if( $upperCompare === -1 || ( $this->bounds & self::UPPER_CONTAIN_NOT and $upperCompare === 0 ) ) {
            return false;
        }

        return true;

    }

    /**
     * Is this interval empty?
     * @return bool
     */
    public function isEmpty()
    {
        return $this->y === 0 && $this->m === 0 && $this->d === 0 && $this->h === 0 && $this->i === 0 && $this->s === 0;
    }

    /**
     * Return a array representing interval information
     * @return array
     */
    public function toArray()
    {
        return array(
            'year' => $this->y,
            'month' => $this->m,
            'day' => $this->d,
            'hour' => $this->h,
            'minute' => $this->i,
            'second' => $this->s,
        );
    }

    /**
     * Return a human readable resentation of the interval
     * @return string
     */

    public function __toString()
    {
        return sprintf(
            '%s"%s","%s"%s',
            $this->bounds & self::LOWER_CONTAIN ? '[' : '(',
            (string) $this->lower,
            (string) $this->upper,
            $this->bounds & self::UPPER_CONTAIN ? ']' : ')'
        );
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quotingInterface )
    {
        return $quotingInterface->quote( $this->__toString() );
    }

}