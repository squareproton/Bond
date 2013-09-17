<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql\Tests;

use Bond\Pg\Result;

use Bond\Sql\Constant;
use Bond\Sql\Query;

use Bond\Pg\Tests\PgProvider;

class QueryTest extends PgProvider
{

    public function testPgQueryWithNothingToDo()
    {

        $db = $this->connectionFactory->get('RW');

        $querys = array(
            "SELECT 1",
            "SELECT * FROM contacts",
            "SELECT '%spanner%'",
            123,
            "%spanner:notarealtypetocheck%"
        );

        foreach( $querys as $query ) {

            $obj = new Query( $query );

            $this->assertSame( (string) $query, $obj->parse($db) );

        }

    }

    public function testPgQueryWithSomethingToDo()
    {

        $db = $this->connectionFactory->get('RW');

        $v = array();

        $v['one'] = 1;

        $q = new Query( "SELECT %one:int%", $v );
        $this->assertSame( $q->parse($db) , "SELECT 1" );
        $q = new Query( "SELECT %one:int%", $v );
        $this->assertSame( $q->parse($db), "SELECT 1" );
        $q = new Query( "SELECT %one:text%", $v );
        $this->assertSame( $q->parse($db), "SELECT '1'" );

        $v['two'] = '2';

        $q = new Query( "SELECT %two:int%", $v );
        $this->assertSame( $q->parse($db), "SELECT 2" );
        $q = new Query( "SELECT %two:%", $v );
        $this->assertSame( $q->parse($db), "SELECT '2'" );
        $q = new Query( "SELECT %two:int|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT 2" );
        $q = new Query( "SELECT %two:|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT '2'" );

        $v['person'] = 'matt';

        $q = new Query( "SELECT %person:%", $v );
        $this->assertSame( $q->parse($db), "SELECT 'matt'" );
        $q = new Query( "SELECT %person:|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT 'matt'" );
        $q = new Query( "SELECT %person:text%", $v );
        $this->assertSame( $q->parse($db), "SELECT 'matt'" );
        $q = new Query( "SELECT %person:text|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT 'matt'" );
        $q = new Query( "SELECT %person:int%", $v );
        $this->assertSame( $q->parse($db), "SELECT 0" );
        $q = new Query( "SELECT %person:int|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT 0" );
        $q = new Query( "SELECT %person:char(1)%", $v );
        $this->assertSame( $q->parse($db), "SELECT 'm'" );
        $q = new Query( "SELECT %person:char(100)|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT 'matt'" );

        $v['empty'] = '';
        $q = new Query( "SELECT %empty:%", $v );
        $this->assertSame( $q->parse($db), "SELECT ''" );
        $q = new Query( "SELECT %empty:int%", $v );
        $this->assertSame( $q->parse($db), "SELECT 0" );
        $q = new Query( "SELECT %empty:int|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT 0" );
        $q = new Query( "SELECT %empty:|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT NULL" );

        $v['whitespace'] = '   ';

        $q = new Query( "SELECT %whitespace:%", $v );
        $this->assertSame( $q->parse($db), "SELECT '   '" );
        $q = new Query( "SELECT %whitespace:|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT NULL" );

        $v['null'] = null;

        $q = new Query( "SELECT %null:%", $v );
        $this->assertSame( $q->parse($db), "SELECT NULL" );
        $q = new Query( "SELECT %null:int%", $v );
        $this->assertSame( $q->parse($db), "SELECT NULL" );
        $q = new Query( "SELECT %null:text%", $v );
        $this->assertSame( $q->parse($db), "SELECT NULL" );
        $q = new Query( "SELECT %null:char(10)%", $v );
        $this->assertSame( $q->parse($db), "SELECT NULL" );

        $v['true'] = true;

        $q = new Query( "SELECT %true:%", $v );
        $this->assertSame( $q->parse($db), "SELECT TRUE" );
        $q = new Query( "SELECT %true:int%", $v );
        $this->assertSame( $q->parse($db), "SELECT 1" );
        $q = new Query( "SELECT %true:text%", $v );
        $this->assertSame( $q->parse($db), "SELECT '1'" );
        $q = new Query( "SELECT %true:char(10)%", $v );
        $this->assertSame( $q->parse($db), "SELECT '1'" );

        $v['false'] = false;

        $q = new Query( "SELECT %false:%", $v );
        $this->assertSame( $q->parse($db), "SELECT FALSE" );
        $q = new Query( "SELECT %false:int%", $v );
        $this->assertSame( $q->parse($db), "SELECT 0" );
        $q = new Query( "SELECT %false:text%", $v );
        $this->assertSame( $q->parse($db), "SELECT ''" );
        $q = new Query( "SELECT %false:char(10)%", $v );
        $this->assertSame( $q->parse($db), "SELECT ''" );

    }

    public function testQueryIn()
    {

        $db = $this->connectionFactory->get('RW');

        $v['in'] = array( 10, 30, 40 );
        $q = new Query( "SELECT %in:in%", $v );
        $this->assertSame( $q->parse($db), "SELECT 10,30,40" );

        $v['in'] = array( '10', '30', '40' );
        $q = new Query( "SELECT %in:in%", $v );
        $this->assertSame( $q->parse($db), "SELECT '10','30','40'" );

    }

    public function testQueryArray()
    {

        $db = $this->connectionFactory->get('RW');

        $v['a'] = array( 10, 30, 40 );

        $q = new Query( "SELECT %a:%", $v );
        $this->assertSame( $q->parse($db), "SELECT ARRAY[10,30,40]" );
        $q = new Query( "SELECT %a:array%", $v );
        $this->assertSame( $q->parse($db), "SELECT ARRAY[10,30,40]" );
        $q = new Query( "SELECT %a:int[]%", $v );
        $this->assertSame( $q->parse($db), "SELECT ARRAY[10,30,40]" );
        $q = new Query( "SELECT %a:text[]%", $v );
        $this->assertSame( $q->parse($db), "SELECT ARRAY['10','30','40']" );
        $q = new Query( "SELECT %a:text[]|cast%", $v );
        $this->assertSame( $q->parse($db), "SELECT ARRAY['10','30','40']::text" );

        // boyce-codd wouldn't be pleased with this. If you're using multi dimensional array's you're probably not cool.
        $v['r'] = array( array(1,2,3), array(3,4,5) );
        $q = new Query( "SELECT %r:%", $v );
        $this->assertSame( $q->parse($db), "SELECT ARRAY[ARRAY[1,2,3],ARRAY[3,4,5]]" );

    }

    public function testQueryJSON()
    {

        $db = $this->connectionFactory->get('RW');

        $v['a'] = '"string"';
        $q = new Query( "SELECT %a:json%", $v );
        $this->assertSame( $q->parse($db), "SELECT '\"string\"'" );

        $v['a'] = 1;
        $q = new Query( "SELECT %a:json%", $v );
        $this->assertSame( $q->parse($db), "SELECT '1'" );

        $v['a'] = json_encode( [1,2] );
        $q = new Query( "SELECT %a:json%", $v );
        $this->assertSame( $q->parse($db), "SELECT '[1,2]'" );

        $v['a'] = "1";
        $q = new Query( "SELECT %a:json%", $v );
        $this->assertSame( $q->parse($db), "SELECT '1'" );

        $v['a'] = 1;
        $q = new Query( "SELECT %a:json|cast%", $v );
        $this->assertSame( $q->parse($db), "SELECT '1'::json" );

    }

    public function testQueryBytea()
    {

        $db = $this->connectionFactory->get('RW');

        // produce a lot of filthy binary data
        $filthyBinaryData = 'hello';
        while( strlen($filthyBinaryData) < 1024*32 ) {
            $filthyBinaryData = gzencode( $filthyBinaryData . base64_encode($filthyBinaryData) );
        }

        $q = new Query( "SELECT %filthyBinaryData:bytea%::bytea" );
        $q->filthyBinaryData = $filthyBinaryData;

        $r = $db->query( $q )->fetch(Result::FETCH_SINGLE | Result::TYPE_DETECT);
        $this->assertSame( $r, $filthyBinaryData );

    }

    public function testCastModifier()
    {

        $db = $this->connectionFactory->get('RW');

        $v['createDate'] = '2001-01-01 00:00:00.000000';

        // timestamp
        $q = new Query( "SELECT %createDate:timestamp|cast%", $v );
        $this->assertSame( $q->parse($db), "SELECT '{$v['createDate']}'::timestamp" );
        $q = new Query( "SELECT %createDate:timestamp|null|cast%", $v );
        $this->assertSame( $q->parse($db), "SELECT '{$v['createDate']}'::timestamp" );
        $v['createDate'] = '';
        $q = new Query( "SELECT %createDate:timestamp|null|cast%", $v );
        $this->assertSame( $q->parse($db), "SELECT NULL::timestamp" );

    }

    public function testCastModifierWithArgument()
    {

        $db = $this->connectionFactory->get('RW');

        $v['value'] = '123';

        $q = new Query( "SELECT %value:text|cast%", $v );
        $this->assertSame( $q->parse($db), "SELECT '123'::text" );
        $q = new Query( "SELECT %value:text|cast()%", $v );
        $this->assertSame( $q->parse($db), "SELECT '123'::text" );
        $q = new Query( "SELECT %value:text|cast(int)%", $v );
        $this->assertSame( $q->parse($db), "SELECT '123'::int" );
        $q = new Query( "SELECT %value:text|cast(sometype)%", $v );
        $this->assertSame( $q->parse($db), "SELECT '123'::sometype" );

    }

    public function testQueryParsingCache()
    {

        $db = $this->connectionFactory->get('RW');

        $v['null'] = '';

        $q = new Query( "SELECT %null:text%, %null:text|null%", $v );
        $this->assertSame( $q->parse($db), "SELECT '', NULL" );

    }

    public function testPgQueryWithNamedArgumentNotPresent()
    {

        $db = $this->connectionFactory->get('RW');

        $obj = new Query( "SELECT %count:int%", array() );

        $this->setExpectedException("Bond\\Database\\Exception\\MissingArgumentException");
        $obj->parse($db);

    }

    public function testVariableSetterMethods()
    {

        // instantiation
        $v = array( 'one' => 1 );
        $q = new Query( "SELECT %one:int%", $v );

        // test getter
        $this->assertSame( $q->one, 1 );
        $this->assertSame( $q->dataGet(), $v );

        // test setter
        $q->one = 2;
        $this->assertSame( $q->one, 2 );

        // test isset
        $this->assertTrue( isset( $q->one ) );
        $this->assertFalse( isset( $q->two ) );

        // test unset
        unset( $q->one );
        $this->assertFalse( isset( $q->one ) );
        $this->assertSame( $q->dataGet(), [] );

        // test addition
        $q->two = 2;
        $this->assertSame( $q->two, 2 );

        // test wholesale replace
        $newData = array( 'three' => 3 );
        $this->assertSame( $q->dataSet( $newData ), $q );
        $this->assertSame( $q->dataGet(), $newData );

        // handling null
        $q->null = null;
        $this->assertSame( $q->null, null );

        $this->setExpectedException('Bond\Exception\BadPropertyException');
        $q->thisPropertyDoesNotExist;

    }

    public function testSqlGet()
    {

        $sql1 = "1 query here";

        $q = new Query( $sql1 );
        $this->assertSame( $q->sqlGet(), $sql1 );

    }

    public function testQuoteIdentifierInQuery()
    {

        $db = $this->connectionFactory->get('RW');
        $q = new Query( "SELECT 'one' AS %i:identifier%" );

        $executeAndReturnFirstKey = function() use ( $q, $db ) {
            $result = $db->query( $q )->fetch( Result::FETCH_SINGLE | Result::FLATTEN_PREVENT );
            list( $firstKey, ) = each( $result );
            return $firstKey;
        };

        $q->i = $i = 'spanner';
        $this->assertSame( $executeAndReturnFirstKey(), $i );

        $q->i = $i = '"';
        $this->assertSame( $executeAndReturnFirstKey(), $i );

        $q->i = $i = '\\\$@"';
        $this->assertSame( $executeAndReturnFirstKey(), $i );

        $q->i = $i = '~][&^2\'"""';
        $this->assertSame( $executeAndReturnFirstKey(), $i );

    }

    public function testSqlConstant()
    {

        $db = $this->connectionFactory->get('RW');

        foreach( array('','text') as $type ) {

            $query = new Query(
                "%name:{$type}%",
                array(
                    'name' => new Constant('DEFAULT')
                )
            );

            $this->assertSame( $query->parse($db), 'DEFAULT' );

        }

    }

    public function testPostgresBitfields()
    {

        $db = $this->connectionFactory->get('RW');

        $tests = array(
            0 => '0',
            1 => '01',
            4 => '100',
            15 => '1111',
            16 => '10000',
        );

        $c = 0;
        foreach( $tests as $int => $bitfield ) {

            $query = new Query( <<<SQL
                SELECT
                    B%bitfield:%::bit(%c:%)::int as i,
                    %int:%::bit(%c:%)::text as t,
                    %int:%::bit(%c:%) as b,
                    %int:%::bit(%c:%)::varbit as vb,
                    %int:varbit|cast% as round_trip
                ;
SQL
                , array(
                    'bitfield' => $bitfield,
                    'c' => ++$c,
                    'int' => $int,
                )
            );

            $result = $db->query( $query );

            // vanilla (ie, check we've not made a mistake setting up the test array
            $plain = $result->fetch( Result::FETCH_SINGLE );
            $this->assertSame( $plain['i'], (string) $int );
            $this->assertSame( $plain['t'], $bitfield );

            // now with casting
            $cast = $result->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );
            $this->assertSame( $cast['b'], $int );
            $this->assertSame( $cast['vb'], $int );
            $this->assertSame( $cast['round_trip'], $int );

        }

    }

}