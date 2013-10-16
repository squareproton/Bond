<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Converter\Tests;

use Bond\Pg\Result;
use Bond\Sql\Raw;
use Bond\Sql\Query;

class ConverterFactoryTest extends \Bond\Pg\Tests\PgProvider
{

    private $exoticArray;

    public function __construct()
    {
        $this->exoticArray = [
            '0',
            'something',
            'somewhere',
            '"',
            "",
                '\\\\',
            '{}',
            '(,,4,)',
            "\\",
            "\\\\",
            "null",
            null,
            "{",
            "'",
            "'''",
            ",",
            "string",
            "string with spaces",
            "",
            "01234567890123456789.000",
            "a",
            "",
            "\n\n",
            "\n\n\r",
        ];

        $this->exoticRecord = $this->exoticArray;
    }

    public function testArrayConverter()
    {

        $db = $this->connectionFactory->get('RW');

        // manually build up exotic array as a values statement
        $quoted = array_map(
            function ($value) use($db) {
                return "(".$db->quote($value) .")";
            },
            $this->exoticArray
        );
        $query = new Raw( "SELECT array_agg(v) FROM (VALUES" . implode(',', $quoted) .")_(v);\n" );
        $viaDb = $db->query($query)->fetch(Result::FETCH_SINGLE|Result::TYPE_DETECT);

        $this->assertSame( $this->exoticRecord, $viaDb );

    }

    public function testQuoteArray()
    {

        $db = $this->connectionFactory->get('RW');

        $query = new Query("SELECT %array:%", ['array' => $this->exoticArray] );
        $viaDb = $db->query($query)->fetch(Result::FETCH_SINGLE|Result::TYPE_DETECT);

        $this->assertSame( $this->exoticArray, $viaDb );

    }

    public function testRecordRender()
    {

        $db = $this->connectionFactory->get('RW');

        // manually build up exotic array as a values statement
        $quoted = array_map( [$db, 'quote'], $this->exoticRecord );
        $query = new Raw( "SELECT ROW(_.*) FROM ( SELECT ". implode(',', $quoted) . ") _;\n" );

        $viaDb = $db->query($query)->fetch(Result::FETCH_SINGLE|Result::TYPE_DETECT);

        $this->assertSame( $viaDb, $this->exoticRecord );

    }

}