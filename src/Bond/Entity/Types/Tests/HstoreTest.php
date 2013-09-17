<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types\Tests;

use Bond\Entity\Types\Hstore;

use Bond\Pg\Connection;
use Bond\Pg\Result;
use Bond\Sql\Query;
use Bond\Sql\Raw;

// use Bond\RecordManager;
// use Bond\Normality\UnitTest\Entity\Store as HstoreEntity;
// use Bond\Normality\Tests\NormalityProvider;

use Bond\Pg\Tests\PgProvider;

class HstoreTest extends PgProvider
{

    public static $trickyKeysAndValues = [
        '\\',
        '"',
        ',',
        '=',
        '>',
        '\\",=>',
        "    ",
        " \n\r\t",
        '\\"'
        /*
        */
    ];

    public function testInit()
    {
        $store = new Hstore();
    }

    public function testUtterlyTrickyStore()
    {

        $storeA = new Hstore();
        foreach( self::$trickyKeysAndValues as $value ) {
            $storeA->$value = $value;
        }

        $storeB = new Hstore( (string) $storeA );

        $this->assertSame(
            json_encode( $storeA ),
            json_encode( $storeB )
        );

        $this->assertTrue( $storeA->isSameAs( $storeB ) );

    }

    public function testIsSameAs()
    {

        $a = new Hstore();
        $b = new Hstore();
        $this->assertTrue( $a->isSameAs( $b ) );
        $a->key = 'value';
        $this->assertFalse( $a->isSameAs( $b ) );
        $b->key = 'value';
        $this->assertTrue( $a->isSameAs( $b ) );

        $a = new Hstore('a=>b,c=>d');
        $b = new Hstore('c=>d,a=>b');
        $this->assertTrue( $a->isSameAs( $b ) );

    }

    public function testFromStringToArray()
    {

        $tests = [
            [ array('a' => 'xx', 'b' => 'yy'), '"a"=>"xx","b"=>"yy"' ],
            [ array('aaa"=>' => 'xx', 'b' => 'yy'), '"aaa\"=>"=>"xx","b"=>"yy"', ],
            [ array('a"aa"' => 'xx', 'b' => 'yy'), '"a\"aa\""=>"xx","b"=>"yy"' ],
            [ array('a"aa"' => 'xx', 'b' => 'y"y'), '"a\"aa\""=>"xx","b"=>"y\"y"' ],
            [ array(), '' ],
        ];

        $obj = new Hstore();
        $method = new \ReflectionMethod( $obj, 'fromStringToArray' );
        $method->setAccessible(true);

        foreach( $tests as $test ) {

            $this->assertSame(
                $test[0],
                $method->invoke( $obj, $test[1] )
            );

            $store = new Hstore( $test[0] );

            $this->assertSame(
                json_encode( $store ),
                json_encode( $test[0] )
            );

        }

    }

    public function testRoundTrip()
    {

        $db = $this->connectionFactory->get('R');

        $a = new Hstore('one=>one, two=>Two');
        $b = new Hstore();

        $query = new Query( <<<SQL
            SELECT
                %a:%::hstore as a,
                %b:%::hstore as b,
                null::hstore as "null"
SQL
,
            array(
                'a' => $a,
                'b' => $b
            )
        );

        $result = $db->query( $query )->fetch(Result::TYPE_DETECT | Result::FETCH_SINGLE);

        $this->assertTrue( $result['a']->isSameAs( $a ) );
        $this->assertTrue( $result['b']->isEmpty() );
        $this->assertTrue( $result['b']->isSameAs( $b ) );
        $this->assertNull( $result['null'] );

    }

//    public function testDataTypes()
//    {
//        $dataTypes = HstoreEntity::r()->dataTypesGet('store');
//        $dataType = $dataTypes['store'];
//
//        $this->assertTrue( $dataType->isEntity( $entity ) );
//        $this->assertSame( $entity, "Hstore" );
//        $this->assertFalse( $dataType->isNormalityEntity() );
//    }
//
//    public function testEntityFetch()
//    {
//        $this->populate();
//        $stores = HstoreEntity::r()->findAll();
//        $this->assertTrue(
//            $stores->randomGet()->get('store') instanceof Hstore
//        );
//    }
//
//    public function testEntitySet()
//    {
//
//        $store = new HstoreEntity();
//        $store->set( 'store', 'one=>one' );
//        $this->assertSame( (string) $store['store'], 'one=>one' );
//
//        $store->set( 'store', '' );
//        $this->assertSame( (string) $store['store'], '' );
//        $this->assertTrue( $store['store']->isSameAs(new Hstore()) );
//
//    }
//
//    public function testRecordManager()
//    {
//
//        $rm = RecordManager::init();
//        $db = $this->connectionFactory->get('RW');
//        $repo = HstoreEntity::r();
//
//        $store = new Hstore('one=>one, xx=>"xx"');
//
//        $entityA = $repo->make(
//            array(
//                'id' => 1,
//                'store' => $store,
//            )
//        );
//
//        $response = $rm->persist($entityA)->flush($db);
//
//        // should be persisted. now clear caches...
//        $repo->cacheInvalidateAll();
//
//        $entityB = $repo->find(1);
//        $this->assertTrue( $entityB->get('store')->isSameAs( $entityA->get('store') ) );
//
//    }

    public function populate()
    {
        $query = new Raw( <<<SQL
            INSERT INTO
                "store" ( id, store )
            VALUES
                ( 1, '"one"=>"one"' ),
                ( 2, '"one"=>"one","two"=>"two"' )
            ;
SQL
        );

        $this->connectionFactory->get('RW')->query( $query );
    }

}