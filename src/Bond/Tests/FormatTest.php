<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Tests;

use Bond\Format;

class FormatTest extends \PHPUnit_Framework_Testcase
{

    const indent8_sql = <<<SQL
        SELECT
            *
        FROM
            sometable
SQL
;

    const indent4_sql = <<<SQL
    SELECT
        *
    FROM
        sometable
SQL
;

    const indent0_sql = <<<SQL
SELECT
    *
FROM
    sometable
SQL
;

    const duplicateLines = <<<TEXT
0

1

2

3

TEXT
;

    const duplicateLinesRemoved = <<<TEXT
0

1

2

3

TEXT
;

    public function testConstruct()
    {

        $a = new Format( self::indent8_sql );
        $b = new Format( explode( "\n", self::indent8_sql ) );

        $this->assertSame(
            (string) $a,
            (string) $b
        );

    }

    public function testGetIndent()
    {

        $format = new Format( self::indent8_sql );
        $this->assertSame( 8, $format->getIndent() );

        $format = new Format( self::indent4_sql );
        $this->assertSame( 4, $format->getIndent() );

        $format = new Format( self::indent0_sql );
        $this->assertSame( 0, $format->getIndent() );

    }

    public function testFormatDeIndent()
    {
        $format = new Format( self::indent8_sql );
        $this->assertSame(
            (string) $format->deIndent(),
            self::indent0_sql
        );
    }

    public function testConstructFromArray()
    {
         $format = new Format( explode( "\n", self::indent0_sql) );
         $this->assertSame( (string) $format, self::indent0_sql );
    }

    public function testIndent()
    {
        $format = new Format( self::indent0_sql );
        $this->assertSame( (string) $format->indent(8), self::indent8_sql );
    }

    public function testComment()
    {

        $format = new Format( self::indent0_sql );
        $format->comment();

        $this->assertNotSame( self::indent0_sql, (string) $format );
        $format->uncomment();
        $this->assertSame( self::indent0_sql, (string) $format );
        $format->uncomment();
        $this->assertSame( self::indent0_sql, (string) $format );

    }

    public function testDuplicateEmptyLinesRemove()
    {
        $format = new Format( self::duplicateLines );
        $format->removeDuplicateEmptyLines();
        $this->assertSame( self::duplicateLinesRemoved, (string) $format );
    }

    public function testSingleLineflock()
    {
        $singleLine = 'single line';
        $format = new Format($singleLine);
        $this->assertSame( (string) $format, $singleLine );
    }

}