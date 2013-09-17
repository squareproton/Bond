<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types\Tests;

use Bond\Entity\Types\Inet;

use Bond\Pg\Connection;
use Bond\Pg\Result;
use Bond\Sql\Query;
use Bond\Sql\Raw;

// use Bond\Normality\UnitTest\Entity\Inet as InetEntity;
// use Bond\Normality\Tests\NormalityProvider;

use Bond\Pg\Tests\PgProvider;

class InetTest extends PgProvider
{

    public function testQueryFetch()
    {

        $this->populate();
        $db = $this->connectionFactory->get('RW');

        $query = new Query( 'SELECT ip FROM inet' );
        $result = $db->query( $query );

        $results = $result->fetch( Result::TYPE_DETECT );
        $this->assertTrue( $results[0] instanceof Inet );

        // null's pass through as nulls
        $query = new Query( 'SELECT null::inet' );
        $result = $db->query( $query )->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );
        $this->assertNull( $result );

    }

    public function testQuery()
    {
        $db = $this->connectionFactory->get('R');
        $query = new Query(
            "%ip:%",
            array(
                'ip' => new Inet('1.1.1.1')
            )
        );
        $this->assertSame( $query->parse($db), "'1.1.1.1'" );
    }

    public function testConstructor()
    {
        $validIps = [
            "192.168.2.1",
            "1.1.1.4",
            "1.1.1.1"
        ];
        foreach( $validIps as $ip ) {
            $inetObj = new Inet( $ip );
            $this->assertSame( (string) $inetObj, $ip );
        }
    }

    public function testInit()
    {

        $this->setExpectedException("InvalidArgumentException");
        $exception = new Inet( '       192.168.2.1' );

    }

//    public function testDataTypes()
//    {
//        $dataTypes = InetEntity::r()->dataTypesGet('ip');
//        $dataType = $dataTypes['ip'];
//
//        $this->assertTrue( $dataType->isEntity( $entity ) );
//        $this->assertSame( $entity, "Inet" );
//        $this->assertFalse( $dataType->isNormalityEntity() );
//    }
//
//    public function testEntityFetch()
//    {
//        $this->populate();
//        $inets = InetEntity::r()->findAll();
//        $this->assertTrue(
//            $inets->randomGet()->get('ip') instanceof Inet
//        );
//    }
//
//    public function testEntitySet()
//    {
//
//        $ip = new InetEntity();
//        $ip->set( 'ip', '1.1.1.1' );
//        $this->assertSame( (string) $ip['ip'], '1.1.1.1' );
//
//        $ip->set( 'ip', '2.2.2.2' );
//        $this->assertSame( (string) $ip['ip'], '2.2.2.2' );
//
//        $ip->set( 'ip', $three = new Inet('3.3.3.3') );
//        $this->assertSame( $ip['ip'], $three );
//
//        $ip->set( 'ip', null );
//        $this->assertNull( $ip['ip'] );
//
//        $ip->set( 'ip', 'not a valid ip address' );
//        $this->assertNull( $ip['ip'] );
//
//    }

    public function populate()
    {
        $query = new Raw( <<<SQL
            INSERT INTO
                "inet" ( ip )
            VALUES
                ( '192.168.0.1'::inet ),
                ( '4.4.2.2'::inet )
            ;
SQL
        );

        $this->connectionFactory->get('RW')->query( $query );
    }

}