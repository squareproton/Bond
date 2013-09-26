<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types;

use Bond\Exception\BadDateTimeException;
use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

use Bond\MagicGetter;

/**
 * @author Pete
 */
class DateTime extends \DateTime implements SqlInterface, \JsonSerializable, \Serializable
{

    use MagicGetter;

    /**
     * Postgres datetime formats
     */
    const POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE = 'Y-m-d H:i:s.u';
    const POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE_NO_MICROSECONDS = 'Y-m-d H:i:s';

    const POSTGRES_DATE = 'Y-m-d';
    const FILESYSTEM_DATE = 'YmdHis';

    /**
     * Microsecond store
     * @param int
     */
    private $microseconds = 0;

    /**
     * Infinity cardinality. -1 = '-infinity', 0 = a normal date, '+infinity'
     * @param int --1|0|1
     */
    private $infinity = 0;

    /**
     * Work around a couple of bugs in PHP's stock DateTime class.
     * Mainly due to microtime specific weirdness
     * @param mixed $time
     * @param mixed $timezone
     */
    public function __construct( $time = 'now', \DateTimeZone $timezone = null )
    {

        // unixtimestamp
        if( is_numeric( $time ) ) {

            if( false !== $pos = strpos( $time, '.' ) ) {
                $this->microseconds = (int) str_pad(
                    substr( $time, $pos + 1 ),
                    6,
                    '0',
                    \STR_PAD_RIGHT
                );
                $time = (int) substr( $time, 0, $pos );
            }

            parent::__construct(
                date( self::POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE, $time ),
                self::fixTimezone( $timezone )
            );
            return;

        }

        $time = trim( $time );
        if( $time === '+infinity' || $time === 'infinity' ) {
            $this->infinity = 1;
            return;
        } elseif( $time === '-infinity' ) {
            $this->infinity = -1;
            return;
        }

        // Check if $time is a SQL timestamp with microseconds.
        // PHP has problems with microseconds rounding unexpectedly.
        if( preg_match( '/^(\d{4})-(\d{2})-(\d{2})[T| ](\d{2}):(\d{2}):(\d{2})(\.(\d+))((\+|-)\d{2})?$/', $time, $matches ) ) {

            // Store the microseconds.
            if( isset( $matches[8] ) ) {
                $this->microseconds = (int) str_pad(
                    $matches[8],
                    6,
                    '0',
                    \STR_PAD_RIGHT
                );
            }

            // Strip the microseconds from the original SQL timestamp.
            parent::__construct(
                date( self::POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE, strtotime($time) ),
                self::fixTimezone( $timezone )
            );
            return;

        }

        try {

            parent::__construct(
                $time,
                self::fixTimezone( $timezone )
            );

        } catch (\Exception $e) {

            throw new BadDateTimeException($time, $e);
        }
    }

    /**
     * Work around PHP Bug where DateTime documentation
     * indicates $timezone should allow null, but doesn't.
     * @param \DateTimeZone $timezone
     */
    private static function fixTimezone( \DateTimeZone $timezone = null )
    {
        return is_null( $timezone )
            ? new \DateTimeZone( date_default_timezone_get() )
            : $timezone;
    }

    /**
     * Serialization interface
     * @return string
     */
    public function serialize()
    {
        return json_encode([
            $this->format(self::POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE_NO_MICROSECONDS),
            $this->microseconds,
            $this->infinity
        ]);
    }

    /**
     * Unserialization interface
     * @return void
     */
    public function unserialize($data)
    {
        $data = json_decode($data, true);
        parent::__construct( $data[0] );
        $this->microseconds = $data[1];
        $this->infinity = $data[2];
    }

    /**
     * Overload \DateTime::createFromFormat
     * @param mixed $format
     * @param mixed $time
     * @param mixed $timezone
     * @return DateTime
     */
    public static function createFromFormat( $format, $time, $timezone = null )
    {

        $dateTime = \DateTime::createFromFormat(
            $format,
            $time,
            self::fixTimezone( $timezone )
        );

        if( !( $dateTime instanceof \DateTime ) ){
            return null;
        }

        return new DateTime(
            $dateTime->format( \DateTime::ISO8601 ),
            self::fixTimezone( $timezone )
        );

    }

    /**
     * Format a date. Infinity aware!
     * @return string
     */
    public function format( $format )
    {
        if( $this->isInfinity() ) {
            return $this->__toString();
        }
        return parent::format( $format );
    }

    /**
     * __toString. Returns DateTime formatted as YYYY-MM-DD HH:mm:ss
     * @return string
     */
    public function __toString()
    {
        if( $this->isInfinity($cardinality) ) {
            return ( $this->infinity === -1 ? '-' : '' ) . "infinity";
        }
        return $this->format( self::POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE_NO_MICROSECONDS ) . $this->microsecondsString();
    }

    /**
     * Format to unix timestamp
     * @return string
     */
    public function toUnixTimestamp()
    {
        return $this->format('U') . $this->microsecondsString();
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return $this->toUnixTimestamp();
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quotingInterface )
    {
        // is infinity?
        if( $this->isInfinity() ) {
            return "'{$this->__toString()}'";
        }

        return sprintf(
            "'%s%s'",
            $this->format( self::POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE_NO_MICROSECONDS ),
            $this->microsecondsString()
        );
    }

    /**
     * Get microseconds string
     * @return string containing the microseconds formatted correctly
     */
    private function microsecondsString()
    {
        if( $this->microseconds ) {
            return rtrim(
                sprintf( ".%06d", $this->microseconds ),
                '0'
            );
        }
        return '';
    }

    /**
     * Truncate any time component from this datetime object.
     * @return DateTime
     */
    public function dateify()
    {
        if( !$this->isInfinity() ) {
            $this->microseconds = 0;
            $this->setTime( 0, 0, 0 );
        }
        return $this;
    }

    /**
     * Is this datetime a reprensation of either +infinity or -infinity
     * @param int $cardinality
     * @return bool
     */
    public function isInfinity( &$cardinality = null )
    {
        $cardinality = $this->infinity;
        return $cardinality !== 0;
    }

    // http://www.php.net/manual/en/class.datetime.php
    // overloaded functions to support infinity
    // TODO. Make a infinity supported DateInterval object

    /**
     * http://www.php.net/manual/en/datetime.add.php
     * @inheritDoc
     */
    public function add( $interval )
    {
        if( $this->isInfinity() ) {
            return $this;
        } elseif (is_string($interval) ) {
            $interval = new \DateInterval($interval);
        }
        return parent::add( $interval );
    }

    /**
     * http://www.php.net/manual/en/datetime.sub.php
     * @inheritDoc
     */
    public function sub( $interval )
    {
        if( $this->isInfinity() ) {
            return $this;
        }
        return parent::sub( $interval );
    }

    /**
     * http://www.php.net/manual/en/datetime.modify.php
     * @inheritDoc
     */
    public function modify( $string )
    {
        if( $this->isInfinity() ) {
            return $this;
        }
        return parent::modify( $interval );
    }

    /**
     * http://www.php.net/manual/en/datetime.setdate.php
     * @inheritDoc
     */
    public function setDate( $year, $month, $day )
    {
        if( $this->isInfinity() ) {
            return $this;
        }
        return parent::setDate( $year, $month, $day );
    }

    /**
     * http://www.php.net/manual/en/datetime.settime.php
     * @inheritDoc
     */
    public function setTime( $hour, $minute, $second = 0, $microsecond = 0)
    {
        if( $this->isInfinity() ) {
            return $this;
        }
        $this->microseconds = $microsecond ? (int) $microsecond : 0;
        return parent::setTime( $hour, $minute, $second );
    }

    /**
     * http://www.php.net/manual/en/datetime.add.php
     * @inheritDoc
     */
    public function setTimestamp($unixtimestamp)
    {
        if( $this->isInfinity() ) {
            return $this;
        }
        return parent::add( $interval );
    }

}