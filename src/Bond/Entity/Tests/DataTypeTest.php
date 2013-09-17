<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Tests;

use Bond\Entity\DataType;

use Bond\Pg\Catalog;
use Bond\Pg\Catalog\Tests\CatalogProvider;

class DataTypeTest extends CatalogProvider
{

    public function testSerialize1()
    {

        $data1 = array(
            'type' => 'int'
        );
        $name1 = 'somename';

        $data2 = array(
            'type' => 'int',
            'default' => null,
        );
        $name2 = 'somename';

        $dataType1 = new DataType( $name1, null, null, $data1 );
        $serialize1 = $dataType1->serialize();
        $unserializedData1 = json_decode( $serialize1, true );
        $this->assertSame( $data1, $unserializedData1[3] );

        $dataType2 = new DataType( $name2, null, null, $data2 );
        $serialize2 = $dataType2->serialize();
        $this->assertSame( $serialize1, $serialize2 );

    }

    public function testUnSerialize1()
    {

        $data1 = array(
            'type' => 'int'
        );
        $name1 = 'somename';

        $dataType1 = new DataType( $name1, null, null, $data1 );
        $serialize1 = $dataType1->serialize();

        $dataType2 = DataType::unserialize( $serialize1 );

        $this->assertSame( $dataType1->data, $dataType2->data );

    }

    public function test__call()
    {

        $data = array(
            'type' => 'int'
        );
        $name = 'somename';

        $dataType = new DataType( $name, null, null, $data );

        $this->assertSame( $dataType->getType(), $data['type'] );
        $this->assertSame( $dataType->isPrimaryKey(), false );
        $this->assertSame( $dataType->isUnique(), false );
        $this->assertSame( $dataType->isNullable(), false );
        $this->assertSame( $dataType->isArray(), false );
        $this->assertSame( $dataType->isFormChoiceText(), false );
        $this->assertSame( $dataType->getLength(), null );
        $this->assertSame( $dataType->getDefault(), null );
        $this->assertSame( $dataType->getNotARealProperty(), null );

        $this->setExpectedException("Bond\\Exception\\BadPropertyException");
        $dataType->notAValidMethodCall();

    }

    public function testMakeFromAttribute()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a1_id = $catalog->pgAttributes->findByIdentifier('a1.id');

        $dataType = DataType::makeFromAttribute( $a1_id );
        $this->assertTrue( $dataType instanceof DataType );

    }

    public function testMakeFromAttributeFormChoiceText()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a1 = $catalog->pgClasses->findOneByName('a1');

        $dataType = DataType::makeFromAttribute( $a1->getAttributeByName('string') );
        $this->assertTrue( $dataType->isFormChoiceText() );

        $dataType = DataType::makeFromAttribute( $a1->getAttributeByName('int4') );
        $this->assertFalse( $dataType->isFormChoiceText() );

    }

    public function testMakeSetFromRelation()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a1 = $catalog->pgClasses->findOneByName('a1');

        $dataTypes = DataType::makeFromRelation( $a1 );

        // not exacly sure how I should be testing this without reproducing all the logic?
        $this->assertTrue( is_array( $dataTypes ) );
        $this->assertSame( count($dataTypes), count( $a1->getAttributes() ) );

    }

    public function testIsSequence()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a1 = DataType::makeFromRelation( $catalog->pgClasses->findOneByName('a1') );

        $this->assertTrue( $a1['id']->isSequence( $sequence ) );
        $this->assertSame( $sequence, 'a1_id_seq' );

        $this->assertFalse( $a1['string']->isSequence( $sequence ) );
        $this->assertNull( $sequence );

    }

    public function testhasDefault()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a1 = DataType::makeFromRelation( $catalog->pgClasses->findOneByName('a1') );

        $this->assertTrue( $a1['id']->hasDefault( $default ) );
        $this->assertTrue( !empty( $default ) );

        $this->assertFalse( $a1['string']->hasDefault( $default ) );
        $this->assertFalse( !empty( $default ) );

    }

    public function testisNormalityEntity()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a1 = DataType::makeFromRelation( $catalog->pgClasses->findOneByName('a1') );

        $this->assertTrue( $a1['foreign_key']->isNormalityEntity( $entity ) );
        $this->assertSame( $entity, 'A2' );

        $this->assertFalse( $a1['cc']->isNormalityEntity( $entity ) );
        $this->assertNull( $entity );

    }

    public function testisEntity()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a1 = DataType::makeFromRelation( $catalog->pgClasses->findOneByName('a1') );

        $this->assertTrue( $a1['create_timestamp']->isEntity( $entity ) );
        $this->assertSame( $entity, 'DateTime' );

        $this->assertFalse( $a1['cc']->isEntity( $entity ) );
        $this->assertNull( $entity );

    }

    public function testisEnum()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a4 = DataType::makeFromRelation( $catalog->pgClasses->findOneByName('a4') );

        $this->assertTrue( $a4['type']->isEnum( $enumName ) );
        $this->assertSame( $enumName, 'enumtype' );

        $this->assertFalse( $a4['name']->isEnum( $enumName ) );
        $this->assertNull( $enumName );

    }

    public function testBool()
    {
        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a1b = DataType::makeFromAttribute( $catalog->pgAttributes->findByIdentifier('a1.b') );

        $this->assertTrue( $a1b->getDefault() );
    }

    public function testGetFormReturnsEmptyArray()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a4 = DataType::makeFromRelation( $catalog->pgClasses->findOneByName('a4') );
        $this->assertSame( $a4['id']->getForm(), array() );

    }

    public function testEntity()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $a4r = $catalog->pgClasses->findOneByName('a4');
        $a4t = DataType::makeFromRelation( $a4r );

        foreach( $a4t as $type ) {
            $this->assertSame( $type->entity, $a4r->getEntityName() );
        }

    }

    public function testEntityWithInheritance()
    {

        $catalog = new Catalog( $this->connectionFactory->get('RW') );
        $datatype = DataType::makeFromAttribute( $catalog->pgAttributes->findByIdentifier('ref_a1_child.a1_child_id') );

        $this->assertTrue( $datatype->isNormalityEntity( $entity ) );
        $this->assertSame( $entity, 'A1child' );

    }

}