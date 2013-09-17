<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Tests;

use Bond\Database\Exception\MoreThanOneRowReturnedException;
use Bond\Pg\Result;
use Bond\Sql\Query;

class ResultTest extends PgProvider
{

    public function testPgResultAccessors()
    {

        $db = $this->connectionFactory->get('RW');

        $q = new Query( "SELECT 1" );

        $result = $db->query( $q );
        $this->assertSame( $result->numRows(), 1 ) ;
        $this->assertSame( $result->numFields(), 1 ) ;

        // bugged in postgres 9.1 - watch out
        //$this->assertSame( $result->affectedRows(), 0 ) ;

        // check we got a pg result resource
        $resource = $result->resource;
        $this->assertTrue( is_resource( $resource ) );
        $this->assertTrue( get_resource_type( $resource ) == 'pgsql result' );

        // timing's we want those
        $timings = $result->timings;
        $this->assertArrayHasKey( 'start', $timings );
        $this->assertArrayHasKey( 'total', $timings );
        $this->assertArrayHasKey( 'parsing', $timings );
        $this->assertArrayHasKey( 'query_parsed', $timings );
        $this->assertArrayHasKey( 'query_executed', $timings );

        $this->assertTrue( $result->query instanceof Query );

    }

    public function testFetch()
    {

        $db = $this->connectionFactory->get('RW');

        # one row, one column, not null result

            $q = new Query( "SELECT 1" );
            $result = $db->query( $q );

            $fetch =  $result->fetch();
            $this->assertSame( array( '1' ), $fetch );

            $fetch =  $result->fetch( Result::FETCH_SINGLE );
            $this->assertSame( '1', $fetch );

            # disable flattening
            $fetch = $result->fetch( Result::FETCH_SINGLE | Result::FLATTEN_PREVENT );
            $this->assertEquals( array('?column?'=>1), $fetch );

        # one row, one column, null result

            $q = new Query( "SELECT NULL" );
            $result = $db->query( $q );

            $fetch = $result->fetch();
            $this->assertSame( array( null ), $fetch );

            $fetch =  $result->fetch( Result::FETCH_SINGLE );
            $this->assertSame( null, $fetch );

            # disable flattening
            $fetch = $result->fetch( Result::FETCH_SINGLE | Result::FLATTEN_PREVENT );
            $this->assertEquals( array('?column?'=> null), $fetch );

        # multi row, one column
            $q = new Query( "SELECT 1 as num UNION SELECT 2 as num" );
            $result = $db->query( $q );

            $fetch = $result->fetch();
            $this->assertSame( array( '1', '2' ), $fetch );

            # disable flattening
            $fetch = $result->fetch( Result::FLATTEN_PREVENT );
//            $fetch = $result->fetch();
            $this->assertEquals(
                array(
                   array( 'num' => 1 ),
                   array( 'num' => 2 ),
                ),
                $fetch
            );

            # single result doesn't work
            $raised = false;
            try {
                $fetch = $result->fetch( Result::FETCH_SINGLE );
            }
            catch ( MoreThanOneRowReturnedException $expected ) {
                $raised = true;
            }
            $this->assertTrue( $raised );

        # zero row, one column, null result

            $q = new Query( "SELECT NULL WHERE FALSE" );
            $result = $db->query( $q );

            $fetch = $result->fetch();
            $this->assertSame( array(), $fetch );

            $fetch = $result->fetch( Result::FETCH_SINGLE );
            $this->assertSame( null, $fetch );

            $fetch = $result->fetch( Result::FETCH_SINGLE | Result::FLATTEN_PREVENT );
            $this->assertSame( null, $fetch );

        # one row, two columns result

            $q = new Query( "SELECT '1' AS one, '2' AS two" );
            $result = $db->query( $q );

            $fetch = $result->fetch();
            $this->assertSame( array( array( 'one' => '1', 'two' => '2' ) ), $fetch );

            $fetch = $result->fetch( Result::FETCH_SINGLE );
            $this->assertSame( array( 'one' => '1', 'two' => '2' ), $fetch );

        # zero rows, two columns result

            $q = new Query( "SELECT '1' AS one, '2' AS two WHERE FALSE" );
            $result = $db->query( $q );

            $fetch = $result->fetch();
            $this->assertSame( array(), $fetch );

            $fetch = $result->fetch( Result::FETCH_SINGLE );
            $this->assertSame( array(), $fetch );

        # multiple rows, two columns result

            $q = new Query( "SELECT '1' AS one, '2' AS two UNION SELECT 'i' AS one, 'ii' AS two" );
            $result = $db->query( $q );

            $fetch = $result->fetch();
            $this->assertSame(
                array(
                    array( 'one' => '1', 'two' => '2', ),
                    array( 'one' => 'i', 'two' => 'ii', ),
                ),
                $fetch
            );

            # single result doesn't work
            $raised = false;
            try {
                $fetch = $result->fetch( Result::FETCH_SINGLE );
            }
            catch ( MoreThanOneRowReturnedException $expected) {
                $raised = true;
            }
            $this->assertTrue( $raised );

    }

    public function testFetchCaching()
    {

        $db = $this->connectionFactory->get('RW');
        $result = $db->query( new Query( "SELECT 1" ) );

        $this->assertSame( count( $result->cacheFetch ), 0 );

        $result->fetch();
        $this->assertSame( count( $result->cacheFetch ), 1 );

        $result->fetch();
        $this->assertSame( count( $result->cacheFetch ), 1 );

        $result->fetch( Result::FETCH_SINGLE );
        $this->assertSame( count( $result->cacheFetch ), 2 );

        $result->fetch( Result::FETCH_SINGLE );
        $this->assertSame( count( $result->cacheFetch ), 2 );

        $result->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );
        $this->assertSame( count( $result->cacheFetch ), 3 );

        $result->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );
        $this->assertSame( count( $result->cacheFetch ), 3 );

    }

    public function testFetchWithResultTypeDetect()
    {

        $db = $this->connectionFactory->get('RW');

        $result = $db->query(
            new Query( <<<SQL
                SELECT
                    1::int AS int,
                    1::int4 as int4,
                    '2'::text AS text,
                    array[1,2,3]::text[] AS "textArray",
                    array[1,2,3] AS array, /*
                    '1'::json as json_int,
                    '"1"'::json as json_string,
                    'null'::json as json_null,
                    '[1]'::json as json_numeric_array,
                    '{"one":1,"two":2}'::json as json_assoc_obj, */
                    true as bool
SQL
            )
        );

        $agnostic = $result->fetch( Result::FETCH_SINGLE | Result::TYPE_AGNOSTIC );
        $detect = $result->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );

        foreach( $agnostic as $value ) {
            $this->assertTrue( is_string($value) );
        }

        $this->assertSame(
            $detect,
            array(
                'int' => 1,
                'int4' => 1,
                'text' => '2',
                'textArray' => array("1","2","3"),
                'array' => array(1,2,3),
                /* depreciated we use a json object now
                'json_int' => 1,
                'json_string' => "1",
                'json_null' => null,
                'json_numeric_array' => [1],
                'json_assoc_obj' => array( 'one' => 1, 'two' => 2 ),
                */
                'bool' => true
            )
        );

    }

    public function testWithResultTypeDetectNullSafe()
    {

        $db = $this->connectionFactory->get('RW');

        $result = $db->query( new Query( "SELECT NULL::int AS int, NULL::text AS text, null::int[] AS array, NULL::bool as bool" ) );

        $detect = $result->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );

        $this->assertSame(
            $detect,
            array(
                'int' => null,
                'text' => null,
                'array' => null,
                'bool' => null,
            )
        );

    }

    public function testCountable()
    {

        $db = $this->connectionFactory->get('RW');

        $n = 5;
        $result = $db->query( new Query("SELECT generate_series(1,$n);") );

        $this->assertSame( count($result), $n );

    }

    public function testResultFetchIteratable()
    {

        $db = $this->connectionFactory->get('RW');

        $n = 5;
        $result = $db->query( new Query("SELECT generate_series(0,{$n} -1) n;") );

        // type not detect
        foreach( $result as $key => $value ) {
            $this->assertSame( (string) $key, $value );
        }

        // detect
        $result->setFetchOptions( Result::TYPE_DETECT );
        foreach( $result as $key => $value ) {
            $this->assertSame( $key, $value );
        }

        // no flatten
        $result->setFetchOptions( Result::FLATTEN_PREVENT );
        foreach( $result as $key => $value ) {
            $this->assertSame(
                array( 'n' => (string) $key ),
                $value
            );
        }

    }

    public function testResultArrayAccess()
    {

        $db = $this->connectionFactory->get('RW');

        $n = 1000;
        $result = $db->query( new Query("SELECT generate_series(0,{$n} -1) n;") );

        // type not detect
        $this->assertSame( $result[123], "123" );

        // detect
        $result->setFetchOptions( Result::TYPE_DETECT );
        $this->assertSame( $result[234], 234 );

        // no flatten
        $result->setFetchOptions( Result::FLATTEN_PREVENT );
        $this->assertSame(
            $result[52],
            array( 'n' => "52" )
        );

    }

}