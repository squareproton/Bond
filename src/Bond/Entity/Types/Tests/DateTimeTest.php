<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types\Tests;

use DateInterval;

use Bond\Entity\Types\DateTime;

use Bond\Pg\Connection;
use Bond\Pg\Result;
use Bond\Sql\Query;

use Bond\Pg\Tests\PgProvider;

class DateTimeTest extends PgProvider
{

    public function testPostgresTimezone()
    {
        $db = $this->connectionFactory->get('RW');
        $this->assertSame( $db->getParameter('timezone'), 'UTC' );
    }

    public function testInistantiation()
    {

        $db = $this->connectionFactory->get('RW');
        $time = $db->query( new Query( <<<SQL
            SELECT
                NOW() AS dt,
                CAST( NOW() AS TIMESTAMP WITHOUT TIME ZONE ) AS dt_no_zone,
                EXTRACT('epoch' FROM now())::int AS unix_timestamp
SQL
        ))->fetch( Result::FETCH_SINGLE );

        $obj1 = new DateTime( $time['dt'] );
        $obj2 = new DateTime( $time['dt_no_zone'] );

        // Gotcha (well, it got me!) - DateTime cannot be instantiated
        // with a unix timestamp unless it is prefixed with an @
        $obj3 = new DateTime( '@'. $time['unix_timestamp'] );

        // right pad our postgres timestamps
        $time['dt_no_zone'] = str_pad( $time['dt_no_zone'], 26, '0', \STR_PAD_RIGHT );
        $time['dt_no_zone'] = rtrim( $time['dt_no_zone'], '0' );

        $this->assertSame( $obj1->parse($db), "'{$time['dt_no_zone']}'" );
        $this->assertSame( (string) $obj3->getTimestamp(), (string) $time['unix_timestamp'] );

    }

    public function testInit()
    {

        $this->assertTrue( (new DateTime('2000-00-00 00:00:00')) instanceof \DateTime );
        $this->assertTrue( (new DateTime(null)) instanceof DateTime );

    }

    public function testInitFromUnixTimestamp()
    {

        $db = $this->connectionFactory->get('R');

        $time = time();
        $date = date( DateTime::POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE_NO_MICROSECONDS, $time );
        $this->assertSame( (string) new DateTime( $time ), "{$date}" );

        $time .= '.123456';
        $dt = new DateTime( $time );
        $this->assertSame(
            $dt->parse($db),
            "'{$date}.123456'"
        );

    }

    public function testCreateFromFormat()
    {

        $this->assertInstanceOf(
            '\Bond\Entity\Types\DateTime',
            DateTime::createFromFormat(
                'Y-m-d\TH:i:s',
                '2011-10-09T08:07:06'
            )
        );

        $this->assertNull(
            DateTime::createFromFormat(
                'Y-m-d H:i:s',
                '2011-10-09T08:07:06'
            )
        );
    }

    public function testMicrotimeError()
    {
        $db = $this->connectionFactory->get('RW');

        $c = 0;
        while( $c++ < 999 ) {
            $time = "2000-01-01T00:00:00.".str_pad($c,6,'0', \STR_PAD_LEFT );
            $time = rtrim($time,'0');
            $obj = new DateTime( $time );
            $time[10] = ' ';
            if( $obj->parse($db) !== "'{$time}'" ) {
                print_r_pre( $time );
                print_r_pre( $obj );
                $this->fail("yeah, we've still got that really annoying microtime bug. See Pete or Matt");
            }
        }

    }

    public function testToString()
    {
        $this->assertSame( '2000-01-01 00:00:00', (string) new DateTime( '2000-01-01 00:00:00.000000' ) );
    }

    public function testDateify()
    {
        $time = new DateTime( "2000-01-01 11:11:11.111111" );
        $time->dateify();
        $this->assertSame( "2000-01-01 00:00:00.000000", $time->format( DateTime::POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE ) );

        $time = new DateTime('infinity');
        $time->dateify();
        $this->assertTrue( $time->isInfinity() );
    }

    public function testIsInfinity()
    {
        $time = new DateTime();
        $this->assertFalse( $time->isInfinity( $cardinality ) );
        $this->assertSame( $cardinality, 0 );
    }

    public function testDateInfinity()
    {

        $time = new DateTime('infinity');
        $this->assertTrue( $time->isInfinity( $cardinality ) );
        $this->assertSame( $cardinality, 1 );

        $time = new DateTime('+infinity');
        $this->assertTrue( $time->isInfinity( $cardinality ) );
        $this->assertSame( $cardinality, 1 );

        $time = new DateTime('-infinity');
        $this->assertTrue( $time->isInfinity( $cardinality ) );
        $this->assertSame( $cardinality, -1 );

    }

    public function testInfinityParse()
    {
        $db = $this->connectionFactory->get('RW');

        $time = new DateTime('+infinity');
        $this->assertSame( $time->parse($db), "'infinity'" );

        $time = new DateTime('-infinity');
        $this->assertSame( $time->parse($db), "'-infinity'" );
    }

    public function testFormat()
    {
        $now = new DateTime();
        $this->assertSame( $now->format('Y-m-d'), date('Y-m-d') );

        $infinity = new DateTime('infinity');
        $this->assertSame( $infinity->format('Y-m-d'), 'infinity' );
    }

    public function testAdd()
    {
        $tomorrow = (new DateTime())->add( new DateInterval('P1D') );
        $this->assertSame( $tomorrow->format('Y-m-d'), date('Y-m-d', time() + 86400 ) );

        $infinity = (new DateTime('infinity'))->add( new DateInterval('P1D') );
        $this->assertSame( $infinity->format('Y'), 'infinity' );
    }

    public function testSub()
    {
        $yesterday = (new DateTime())->sub( new DateInterval('P1D') );
        $this->assertSame( $yesterday->format('Y-m-d'), date('Y-m-d', time() - 86400 ) );

        $infinity = (new DateTime('infinity'))->sub( new DateInterval('P1D') );
        $this->assertSame( $infinity->format('Y'), 'infinity' );
    }

    public function testFilthy()
    {
        $this->setExpectedException('Bond\\Exception\\BadDateTimeException');
        $dateTime = new DateTime('blatantly not a date');
    }

    public function testMicrosecondsLeadingZero()
    {
        $ts = "1234567890.1";
        $datetime = new DateTime( $ts );
        $this->assertSame( $datetime->microseconds, 100000 );
    }

    public function testToUnixTimestamp()
    {
        $ts = "1234567890.002001";
        $datetime = new DateTime( $ts );
        $this->assertSame( $ts, $datetime->toUnixTimestamp() );
    }

    public function testSerializeUnseralize()
    {
        $d1 = new DateTime( "1234567890.1" );
        $d2 = unserialize( serialize( $d1 ) );
        $this->assertSame( (string) $d1, (string) $d2 );
    }

}