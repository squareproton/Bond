<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Tests;

use Bond\Pg;
use Bond\Pg\ConnectionFactory;
use Bond\Pg\Resource;
use Bond\Pg\Result;

use Bond\Sql\Query;

class PgTest extends PgProvider
{

    public function testConnectionHasBeenMade()
    {
        $db = $this->connectionFactory->get('RW');
        $this->assertTrue( $db instanceof Pg );
    }

    public function testResourceGet()
    {
        $db = $this->connectionFactory->get('RW');
        $this->assertTrue( $db->resource instanceof Resource );
    }

    public function testNameGetterAndSetter()
    {
        $db = $this->connectionFactory->get('RW');
        $this->assertSame( $db->name, 'RW' );
    }

    public function testQueryWithGoodArguments()
    {
        $db = $this->connectionFactory->get('RW');
        $this->assertTrue(
            $db->query( new Query("SELECT 1") ) instanceof Result
        );
    }

    public function testQueryWithBadSql_SyntaxError()
    {

        $db = $this->connectionFactory->get('RW');
        $query = new Query( 'SELECT FROM LIMIT GROUP BY;' );

        try {
            $result = $db->query( $query );
            $this->fail("should have excepted");
        } catch( \Bond\Pg\Exception\States\State42601 $e ) {
            $this->assertTrue(true);
        }

    }

    public function testQueryWithBadSql_Allowed()
    {

        $db = $this->connectionFactory->get('RW');
        $query = new Query( 'DROP DATABASE "bondzai";' );

        $this->setExpectedException( 'Bond\Pg\Exception\QueryException' );
        $db->query( $query );
    }

    public function testQueryWithBadSql_NotFound()
    {
        $db = $this->connectionFactory->get('RW');
        $query = new Query( 'SELECT * FROM "tableDoesNotExist"' );

        $this->setExpectedException('Bond\Pg\Exception\QueryException');
        $result = $db->query( $query );
    }

    public function testParameterValidator()
    {
        $this->assertTrue( Pg::isParameterAllowed('bond.spanner') );
        $this->assertTrue( Pg::isParameterAllowed('bond.monkey') );
        $this->assertTrue( Pg::isParameterAllowed('search_path') );

        $this->setExpectedException("InvalidArgumentException");
        Pg::isParameterAllowed('bond.;');
    }

    public function testParameterSettings1()
    {

        $db = $this->connectionFactory->get('RW');
        $pathOriginal = $db->getParameter('search_path');

        // hack to work around a postgres search_path set bug
        // Try executing "SET search_path TO "$user",public; SHOW search_path;" and notice the lack of whitespace
        //$pathOriginal = implode( ',', array_map( 'ltrim', explode( ",", $pathOriginal ) ) );
        // $pathOriginal = str_replace(',', ', ', $pathOriginal);

        $db->setParameter( 'search_path', $pathOne = 'app' );
        $this->assertSameWhitespaceIgnored( $db->getParameter('search_path'), $pathOne );

        $db->restoreParameter('search_path');
        $this->assertSameWhitespaceIgnored( $db->getParameter('search_path'), $pathOriginal );

        $db->setParameter( 'search_path', $pathTwo = 'import', 'two' );
        $this->assertSameWhitespaceIgnored( $db->getParameter('search_path'), $pathTwo );

        $db->restoreParameter('search_path');
        $this->assertSameWhitespaceIgnored( $db->getParameter('search_path'), $pathOriginal );

        $db->restoreParameter('search_path', 'two' );
        $this->assertSameWhitespaceIgnored( $db->getParameter('search_path'), $pathTwo );

        $db->restoreParameter('search_path');
        $this->assertSameWhitespaceIgnored( $db->getParameter('search_path'), $pathOriginal );

    }

    public function testQuoteIdent()
    {

        $db = $this->connectionFactory->get('RW');

        $this->assertSame( $db->quoteIdent('spanner'), '"spanner"' );
        $this->assertSame( $db->quoteIdent('spanner.monkey'), '"spanner"."monkey"' );
        $this->assertSame( $db->quoteIdent('spanner.monkey.fish'), '"spanner"."monkey"."fish"' );
        $this->assertSame( $db->quoteIdent(''), '""' );
        $this->assertSame( $db->quoteIdent('"'), '""""' );
        $this->assertSame( $db->quoteIdent('\\'), '"\\"' );

    }

    public function testNotice()
    {

        $db = $this->connectionFactory->get('RW');

        $query = new Query( <<<SQL
DO language plpgsql $$
DECLARE
    c integer default 10;
BEGIN
    WHILE c > 0 LOOP
        RAISE NOTICE 'n-%', c;
        c := c - 1;
    END LOOP;
END $$;
SQL
        );

        $db->query( $query );

        $notices = $db->getLastNotice();

    }

    public function testSerializeUnserialize()
    {

        $db1 = $this->connectionFactory->get('RW');

        $this->setExpectedException('Bond\Exception\DepreciatedException');
        serialize( $db1 );

    }

    private function assertSameWhitespaceIgnored( $a, $b )
    {
        $this->assertSame(
            str_replace(' ', '', $a),
            str_replace(' ', '', $b)
        );
    }

}