<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types\Tests;

use Bond\Sql\Raw;
use Bond\Pg\TypeConversion\DateRange as DateRangeTypeConversion;
use Bond\Pg\Tests\PgProvider;

use Bond\Entity\Types\DateTime;
use Bond\Entity\Types\DateRange;

class DateRangeTest extends PgProvider
{

    public function testConstruct()
    {

        $l = new DateTime('2001-01-01');
        $u = new DateTime('2001-01-02');

        $range = new DateRange( $l, $u );

        $this->assertSame( $range->lower, $l );
        $this->assertSame( $range->upper, $u );

    }

    public function testContains()
    {

        $l = new DateTime('2001-01-01');
        $u = new DateTime('2001-01-02');

        $r_lc_uc = new DateRange( $l, $u, DateRange::LOWER_CONTAIN | DateRange::UPPER_CONTAIN );
        $this->assertTrue( $r_lc_uc->contains($l) );
        $this->assertTrue( $r_lc_uc->contains($u) );

        $r_lcn_uc = new DateRange( $l, $u, DateRange::LOWER_CONTAIN_NOT | DateRange::UPPER_CONTAIN );
        $this->assertFalse( $r_lcn_uc->contains($l) );
        $this->assertTrue( $r_lcn_uc->contains($u) );

        $r_lc_ucn = new DateRange( $l, $u, DateRange::LOWER_CONTAIN | DateRange::UPPER_CONTAIN_NOT );
        $this->assertTrue( $r_lc_ucn->contains($l) );
        $this->assertFalse( $r_lc_ucn->contains($u) );

        $r_lcn_ucn = new DateRange( $l, $u, DateRange::LOWER_CONTAIN_NOT | DateRange::UPPER_CONTAIN_NOT );
        $this->assertFalse( $r_lcn_ucn->contains($l) );
        $this->assertFalse( $r_lcn_ucn->contains($u) );

    }

    public function testMakeFromString()
    {
        $l = new DateTime('2001-01-01');
        $u = new DateTime('2001-01-02');
        $range = new DateRange( $l, $u, DateRange::LOWER_CONTAIN | DateRange::UPPER_CONTAIN );

        $this->assertTrue( DateRange::makeFromString( (string) $range ) instanceof DateRange );
        $this->assertSame( (string) DateRange::makeFromString( (string) $range ), (string) $range );

        $range = new DateRange( $l, $u, DateRange::LOWER_CONTAIN_NOT | DateRange::UPPER_CONTAIN_NOT );
        $this->assertSame( (string) DateRange::makeFromString( (string) $range ), (string) $range );
    }

    public function testSqlInterface()
    {

        $ranges = new Raw( <<<SQL
            SELECT
                (
                    CASE WHEN random() < 0.5 THEN '[' ELSE '(' END ||
                    '"now",' || NOW() + '10 year'::interval * random() ||
                    CASE WHEN random() < 0.5 THEN ']' ELSE ')' END
                )::tsrange
            FROM
                generate_series( 1, 100 )
            ;
SQL
        );

        $db = $this->connectionFactory->get('RW');

        $converter = new DateRangeTypeConversion('daterange');

        foreach( $db->query($ranges)->fetch() as $range )  {
            $this->assertSame(
                $converter($range)->parse($db),
                "'{$range}'"
            );
        }

    }

}