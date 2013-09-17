<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types\Tests;

use Bond\Entity\Base;
use Bond\Entity\Types\Json;

use Bond\Normality\UnitTest\Entity\Json as JsonEntity;

use Bond\Pg\Connection;
use Bond\Pg\Result;
use Bond\Sql\Query;
use Bond\Sql\Raw;

use Bond\RecordManager;

use Bond\Exception\BadJsonException;

use Bond\Normality\Tests\NormalityProvider;

use Bond\Pg\Tests\PgProvider;

class JsonTest extends PgProvider
{

    private $samples = array(
        'null' => null,
        'bool' => true,
        'int' => 1,
        'float' => 1.23,
        'string' => "string",
        'string_filthy' => "\\\"'",
        'array_numeric' => [1,2,3],
        'array_assoc' => array( 'one' => 1, 'two' => 2, 'three' => 3 ),
    );

    public function testAssertClassConstantsConsistent()
    {
        $this->assertSame( Base::VALIDATE_DISABLE, Json::VALIDATE_DISABLE );
        $this->assertSame( Base::VALIDATE_STRIP, Json::VALIDATE_STRIP );
        $this->assertSame( Base::VALIDATE_EXCEPTION, Json::VALIDATE_EXCEPTION );
    }

    public function testConstructorBadType()
    {
        $this->setExpectedException("Bond\Exception\BadTypeException");
        new Json( 124 );
    }

    public function testConstructorBadJSONDefault()
    {
        $this->setExpectedException("Bond\\Exception\\BadJsonException");
        new Json( "spanner" );
    }

    public function testConstructorBadJSONValidateStrip()
    {
        $json = new Json( "spanner", Json::VALIDATE_STRIP );
        $this->assertNull( $json->get() );
    }

    public function testConstructorBadJSONValidateIgnore()
    {
        $json = new Json( "spanner", Json::VALIDATE_DISABLE );
        $this->assertFalse( $json->isValid( $exception ) );
        $this->assertTrue( $exception instanceof BadJsonException );
        $this->setExpectedException("Bond\\Exception\\BadJsonException");
        $json->get();
    }

    public function testConstructorBadValidateOption()
    {
        $this->setExpectedException("Bond\\Exception\\UnknownOptionException");
        $json = new Json( "1", 'not a real option' );
    }

    public function testGet()
    {
        foreach( $this->samples as $value ) {
            $json = new Json(json_encode($value));
            $this->assertSame( $value, $json->get() );
            $this->assertSame( json_encode($value), (string) $json );
            $this->assertSame( json_encode($value, JSON_PRETTY_PRINT), $json->getPretty() );
        }
    }

    public function testQueryFetch()
    {

        $this->populate();

        $query = new Query( 'SELECT json FROM json' );
        $result = $this->connectionFactory->get('RW')->query( $query );

        $results = $result->fetch( Result::TYPE_DETECT );
        $this->assertTrue( $results[0] instanceof Json );

        // null's pass through as nulls
        $query = new Query( 'SELECT null::json' );
        $result = $this->connectionFactory->get('RW')->query( $query )->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );
        $this->assertNull( $result );

    }

    public function testQuery()
    {
        $db = $this->connectionFactory->get('R');
        $query = new Query(
            "%json:%",
            array(
                'json' => new Json('"spanner"')
            )
        );
        $this->assertSame( $query->parse($db), "'\"spanner\"'" );
    }

    public function testMakeFromObject()
    {

        $obj = new \stdClass();
        $json = Json::makeFromObject( $obj );
        $this->assertTrue( $json instanceof Json );
        $this->assertTrue( $json->isValid() );
        $this->assertSame( $obj, $json->get() );
        $this->assertSame( (string) $json, '{}' );

    }

    public function populate()
    {

        $db = $this->connectionFactory->get('RW');

        $values = [];
        foreach( $this->samples as $type => $value ) {
            $values[] = sprintf(
                "( %s, %s::json )",
                $db->quote( $type ),
                $db->quote( json_encode( $value ) )
            );
        }

        $values = implode( ", ", $values );

        $query = new Raw( <<<SQL
            INSERT INTO
                "json" ( id, json )
            VALUES
                {$values}
            ;
SQL
        );

        $db->query( $query );

    }

//    public function testDataTypes()
//    {
//        $dataTypes = JsonEntity::r()->dataTypesGet('json');
//        $dataType = $dataTypes['json'];
//
//        $this->assertTrue( $dataType->isEntity( $entity ) );
//        $this->assertSame( $entity, "Json" );
//        $this->assertFalse( $dataType->isNormalityEntity() );
//    }
//
//
//    public function testEntityFetch()
//    {
//        $this->populate();
//        $inets = JsonEntity::r()->findAll();
//        $this->assertTrue(
//            $inets->randomGet()->get('json') instanceof Json
//        );
//    }
//
//    public function testEntitySet()
//    {
//
//        $json = new JsonEntity();
//        $json->set( 'json', '1' );
//        $this->assertSame( $json['json']->get(), 1 );
//
//        $json->set( 'json', '[1,2,3]' );
//        $this->assertSame( $json['json']->get(), range(1,3) );
//
//        $json->set( 'json', [1,2,3] );
//        $this->assertSame( (string) $json['json'], '[1,2,3]' );
//        $this->assertSame( $json['json']->get(), range(1,3) );
//
//        $json->set( 'json', null );
//        $this->assertNull( $json['json'] );
//
//        $json->set( 'json', [] );
//        $this->assertSame( [], $json['json']->get() );
//
//        $json->set( 'json', 123 );
//        $this->assertSame( 123, $json['json']->get() );
//
//    }
//
//    public function testEntitySetValidation()
//    {
//        $json = new JsonEntity();
//        $json->set('json', $badJson = 'no valid json', Base::VALIDATE_DISABLE );
//        $this->assertSame( (string) $json['json'], $badJson );
//
//        $json = new JsonEntity();
//        $json->set('json', $badJson, Base::VALIDATE_STRIP );
//        $this->assertSame( $json['json']->get(), null );
//
//        $json = new JsonEntity();
//        $this->setExpectedException("Bond\\Exception\\BadJsonException");
//        $json->set( 'json', 'no valid json' );
//    }
//
//    public function testInsertIntoDatabase()
//    {
//
//        $json = new JsonEntity();
//        $json->set('id', 'testInsert');
//        $json->set('json', [] );
//        RecordManager::init()->persist( $json )->flush( $this->connectionFactory->get('RW') );
//
//        $json = JsonEntity::r()->findOneById('testInsert');
//        $this->assertSame( $json['json']->get(), [] );
//    }

}