<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Set\Tests;

use Bond\Pg\Connection;
use Bond\Pg\Result;
use Bond\Sql\Query;

use Bond\Set;
use Bond\Set\Integer;
use Bond\Set\Text;

use Bond\Pg\Tests\PgProvider;

class SetVsQueryTest extends PgProvider
{

    public function testSqlIndentifier()
    {

        $set = new Integer(5);
        $this->assertSame( $set->sqlIdentifier, null );

        $set->sqlIdentifier = 'n';
        $this->assertSame( $set->sqlIdentifier, 'n' );

        $this->assertSame( $set->sqlIdentifierSet( '123' ), $set );
        $this->assertSame( $set->sqlIdentifier, '123' );

    }

    public function testQueryQuoteSafe()
    {

        $db = $this->connectionFactory->get('R');

        $set = new Integer();
        $set->sqlIdentifierSet('n');

        $this->assertSame(
            $set->parse( $db ),
            "FALSE"
        );

        $set->add('-');
        $this->assertSame(
            $set->parse( $db ),
            "( n IS NOT NULL )"
        );

    }

    public function testQueryQuoteSafeWithValues()
    {

        $db = $this->connectionFactory->get('R');

        $set = new Integer(6,10);
        $set->sqlIdentifierSet('n');

        $this->assertSame(
            $set->parse($db),
            "( n IN (6,10) )"
        );

        $set = new Integer('-6');
        $set->sqlIdentifierSet('n');

        $this->assertSame(
            $set->parse($db),
            "( n <= 6 )"
        );

        $set = new Integer('6-');
        $set->sqlIdentifierSet('n');

        $this->assertSame(
            $set->parse($db),
            "( n >= 6 )"
        );

    }

    public function testTextToQuery()
    {

        $db = $this->connectionFactory->get('R');

        $set = new Text('a','b');
        $set->sqlIdentifierSet('n');

        $this->assertSame(
            $set->parse($db),
            "( n IN ('a','b') )"
        );

    }

    public function testIntegerToQueryForRealz()
    {

        $db = $this->connectionFactory->get('R');

        $set = new Integer();
        $set->sqlIdentifierSet( 'n' );

        $query = new Query(
            "SELECT n FROM ( SELECT generate_series( -1000, 1000 ) n ) _ WHERE %set:%",
            array(
                'set' => $set
            )
        );

        $set->none()->add('-10');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, range(-1000, 10) );

        $set->none()->add(10,20);
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, array(10,20) );

        $set->none()->add(10,20,10000);
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, array(10,20) );

        $set->none()->add('10,20,30-32');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, array(10,20,30,31,32) );

        $set->none()->add('-\-999,2,30-32,999-');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, array(-1000,-999,2,30,31,32,999,1000) );

    }

    public function testIntegerToQueryNowWithNulls()
    {

        $db = $this->connectionFactory->get('R');

        $set = new Integer();
        $set->sqlIdentifierSet( 'n' );

        $query = new Query(
            "SELECT n FROM ( VALUES (NULL),(1),(2),(3),(4),(5),(6),(7),(8) ) AS _( n) WHERE %set:%",
            array(
                'set' => $set
            )
        );

        $set->none()->add('\\0,1');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, array( null, 1 ) );

        $set->none()->add('1-8');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, range(1,8) );

        $set->all();
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( count( $result ), 9 );

    }

    public function testTextToQueryForRealz()
    {

        $db = $this->connectionFactory->get('R');

        $set = new Text();
        $set->sqlIdentifierSet( 'n' );

        $query = new Query(
            "SELECT n FROM ( VALUES ('a'),('b'),('c'),('d'),('e'),('f'),('g'),('h'),('i'),('j'),('k'),('l'),('m'),('n'),('o'),('p'),('q'),('r'),('s'),('t'),('u'),('v'),('w'),('x'),('y'),('z') ) AS _( n) WHERE %set:%",
            array(
                'set' => $set
            )
        );

        $set->none()->add('a');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, array('a') );

        $set->none()->add('a,b,c,f');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, str_split("abcf") );

        $set->none()->add('-c,x-');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, str_split("abcxyz") );

        $set->none()->add('c-e');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, str_split("cde") );

        $set->none()->add('cat');
        $result = $db->query( $query )->fetch( Result::TYPE_DETECT );
        $this->assertSame( $result, array() );

    }

}