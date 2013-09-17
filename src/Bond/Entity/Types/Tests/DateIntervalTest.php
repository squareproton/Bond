<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types\Tests;

use Bond\Entity\Types\DateInterval;

use Bond\Pg\Result;
use Bond\Sql\Raw;
use Bond\Sql\Query;

use Bond\Pg\Tests\PgProvider;

class DateIntervalTest extends PgProvider
{

    public function testConstruct()
    {
        $a = new DateInterval('P1Y2M3DT4H5M6S');
        $this->assertTrue( $a instanceof \DateInterval );

        $aArray = $a->toArray();
        $b = new DateInterval($aArray);

        $this->assertSame( $a->toArray(), $b->toArray() );

    }

    public function testSqlInterface()
    {

        $generateIntervals = new Raw("SELECT ('10 year'::interval * random())::interval(0) FROM generate_series(1, 10);");
        $selectInterval = new Query("SELECT %interval:%::interval(0)");

        $db = $this->connectionFactory->get('R');
        $db->setParameter('intervalstyle', 'iso_8601');

        foreach( $db->query($generateIntervals) as $original ) {

            $selectInterval->interval = new DateInterval( $original );

            // test a php roundtrip doesn't alter the interval value in any way
            $this->assertSame(
                $db->query($selectInterval)->fetch(Result::FETCH_SINGLE),
                $original
            );

        }

    }

    public function testPostgresTypeDetect()
    {

        $intervals = new Raw( <<<SQL
            SELECT
                '1 year'::interval year,
                '0.001 second'::interval(0) zero,
                 null::interval as n
SQL
        );

        $db = $this->connectionFactory->get('R');
        $db->setParameter('intervalstyle', 'iso_8601');

        $intervals = $db->query($intervals)->fetch(Result::TYPE_DETECT | Result::FETCH_SINGLE);

        $this->assertTrue( $intervals['interval'] instanceof DateInterval );
        $this->assertTrue( $intervals['zero']->isEmpty() );
        $this->assertNull( $intervals['n'] );

    }

    public function testIsEmpty()
    {

        $interval = new DateInterval("P0D");
        $this->assertTrue( $interval->isEmpty() );

        $interval = new DateInterval("P1D");
        $this->assertFalse( $interval->isEmpty() );

    }

    public function testSerializeUnseralize()
    {
        $i1 = new DateInterval("P0D");
        $s1 = serialize( $i1 );
        $i2 = unserialize( $s1 );

        $this->assertSame( (string) $i1, (string) $i2 );
    }

}