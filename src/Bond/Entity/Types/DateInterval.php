<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;
use Bond\Exception\NotImplementedYet;

use Serializable;

/**
 * @author Pete
 */
class DateInterval extends \DateInterval implements SqlInterface, Serializable, \JsonSerializable
{

    const POSTGRES_ISO_8601 = 'P%yY%mM%dDT%hH%iM%sS';

    private static $arrayMapping = array(
        'year' => 'y',
        'month' => 'm',
        'day' => 'd',
        'hour' => 'h',
        'minute' => 'i',
        'second' => 's',
    );

    /**
     * Add ability to instantiate this interval with a array
     * @param mixed $interval
     */
    public function __construct( $interval, $invert = false )
    {
        if( is_string($interval) ) {
            parent::__construct( $interval );
        } elseif( is_array($interval) ) {
            // properly instantiate a empty interval (it looks like parent::__construct() sets properties invert and days which would be otherwise unset
            parent::__construct('PT0S');
            foreach( array_intersect_key( $interval, self::$arrayMapping ) as $key => $value ) {
                $key = self::$arrayMapping[$key];
                $this->$key = (int) $value;
            }
        } else {
            throw new NotImplementedYet();
        }
        if( $invert ) {
            $this->invert = 1;
        }
    }

    /**
     * Serialization interface
     * @return string
     */
    public function serialize()
    {
        return $this->format( self::POSTGRES_ISO_8601 );
    }

    /**
     * Unserialization interface
     * @return void
     */
    public function unserialize($data)
    {
        parent::__construct($data);
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return $this->serialize();
    }

    /**
     * Standard initalise. Useful for array_map
     * @param $time
     * @return DateInterval|null
     */
    public static function init($time = null)
    {
        try {
            $interval = new self( $time );
            return $interval;
        } catch( \Exception $e ) {
            return null;
        }
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
     * @desc Postgres compatible string representation of a time interval
     */
    public function __toString()
    {
        return $this->format( self::POSTGRES_ISO_8601 );
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quotingInterface )
    {
        return $quotingInterface->quote( $this->format( self::POSTGRES_ISO_8601 ) );
    }

}