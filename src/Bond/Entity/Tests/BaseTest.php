<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Tests;

use Bond\Entity\Base;
use Bond\Entity\Tests\TestEntities\Base as EBT;

// input validation
class EBT_VALIDATE_EXCEPTION extends EBT {
    protected static $inputValidate = self::VALIDATE_EXCEPTION;
}

class EBT_VALIDATE_STRIP extends EBT {
    protected static $inputValidate = self::VALIDATE_STRIP;
}

class EBT_VALIDATE_DISABLE extends EBT {
    protected static $inputValidate = self::VALIDATE_DISABLE;
}

// readonly
class EBT_FULL_ACCESS extends EBT {
    protected static $isReadOnly = self::FULL_ACCESS;
}
class EBT_READONLY_DISABLE extends EBT {
    protected static $isReadOnly = self::READONLY_DISABLE;
}
class EBT_READONLY_EXCEPTION extends EBT {
    protected static $isReadOnly = self::READONLY_EXCEPTION;
}

class BaseTest extends \PHPUnit_Framework_Testcase
{

    public function testInheritance()
    {

        $obj = new EBT();

        $this->assertTrue( $obj instanceof EBT );
        $this->assertTrue( $obj instanceof Base );

    }

    public function testConstructorNull()
    {
        $obj = new EBT(null);

        $this->assertTrue( $obj->isLoaded() );
        $this->assertTrue( $obj->isChanged() );
    }

    public function testConstructorArray()
    {

        $data = array( 'id' => 123, 'name' => 'name-123' );
        $obj = new EBT( $data );

        $this->assertSame( $obj->get('id'), $data['id'] );
        $this->assertSame( $obj->get('name'), $data['name'] );

    }

    public function testConstructorLateLoadKey()
    {

        $obj = new EBT( '123' );
        $this->assertFalse( $obj->isLoaded() );
        $this->assertTrue( $obj->issetProperty('id') );
        $this->assertFalse( $obj->isChanged() );

    }

    public function testConstructorBadArgument()
    {

        $this->setExpectedException( "InvalidArgumentException" );
        $obj = new EBT( new \stdClass() );

    }

    public function testConstructor_VALIDATE_EXCEPTION()
    {

        $data = array('id' => 123, 'name' => "name", 'monkey' => 'banana' );

        $this->setExpectedException('Bond\\Entity\\Exception\\UnexpectedPropertyException');
        $obj = new EBT_VALIDATE_EXCEPTION( $data );

    }

    public function testConstructor_VALIDATE_DISABLE()
    {

        $data = array('id' => 123, 'name' => "name", 'monkey' => 'banana' );
        $obj = new EBT_VALIDATE_DISABLE( $data );

        $this->assertSame( $obj->data, $data );

        $this->assertTrue( $obj->hasProperty('monkey'));
        $this->assertFalse( $obj->hasProperty('not a valid property'));

    }

    public function testConstructor_VALIDATE_STRIP()
    {

        $data = array('id' => 123, 'name' => "name-123" );
        $obj = new EBT_VALIDATE_STRIP( $data + array( 'monkey' => 'banana' ) );

        $this->assertSame( $obj->data, $data );
        $this->assertFalse( $obj->hasProperty('monkey') );

    }

    public function testCopy()
    {

        $id = 'id';
        $name = 'name';

        $entity = new EBT( array('id'=>$id,'name'=>$name) );
        $copy = $entity->copy();
        $this->assertSame( $copy['id'], null );
        $this->assertSame( $entity['name'], $copy['name'] );

    }

    // test set
    public function testSet()
    {

        $obj = new EBT(
            array(
                'id' => null,
                'name' => null
            )
        );

        $this->assertFalse( $obj->set( 'id', 456 ) );
        $this->assertSame( $obj->get( 'id' ), null );
        $this->assertFalse( $obj->isChanged() );

        $name_initial = $obj->get('name');

        $name = "name-456";
        $this->assertTrue( $obj->set( 'name', $name ) );
        $this->assertSame( $obj->get( 'name' ), $name );

        $this->assertTrue( $obj->isChanged() );

        // revert name back and see if things have changed back
        $this->assertTrue( $obj->set( 'name', $name_initial ) );
        $this->assertSame( $obj->get( 'name' ), $name_initial );

        $this->assertFalse( $obj->isChanged() );

    }

    public function testSetByArray()
    {

        $obj = new EBT();

        $this->assertSame( $obj->set( array('name'=>'spanner') ), 1 );
        $this->assertSame( $obj->get('name'), 'spanner' );

        $data = array('id'=>222, 'name'=> 222);

        $this->assertSame( $obj->set( array() ), 0 );
        $this->assertSame( $obj->set( $data ), 1 );

    }

    public function testSetThirdArgumentDefaultException()
    {

        $obj = new EBT();
        $this->setExpectedException( "Bond\\Entity\\Exception\\BadPropertyException" );
        $obj->set('badproperty', true );

    }

    public function testSetThirdArgumentBadArg()
    {

        $obj = new EBT();
        $this->setExpectedException( "InvalidArgumentException" );
        $obj->set( 'badproperty', true, 'not a acceptable constant' );

    }

    public function testSetThirdArgumentBadArg2()
    {

        $obj = new EBT();
        $this->setExpectedException( "InvalidArgumentException" );
        $obj->set( array( 'badproperty' => true ), null, 'not a acceptable constant' );

    }

    public function testSetThirdArgumentRelax()
    {

        $obj = new EBT();

        $this->assertFalse( $obj->set('badproperty', true, Base::VALIDATE_STRIP ) );

        $this->assertSame( $obj->set( array( 'badproperty' => true ), null , Base::VALIDATE_STRIP ), 0 );
        $this->assertFalse( $obj->hasProperty('badproperty') );

    }

    public function testSetThirdArgumentAllow()
    {

        $obj = new EBT();

        $this->assertTrue( $obj->set('badproperty', true, Base::VALIDATE_DISABLE ) );
        $this->assertTrue( $obj->hasProperty('badproperty') );
        $this->assertSame( $obj->get('badproperty'), true );

    }

    public function test_FULL_ACCESS()
    {

        $obj = new EBT_FULL_ACCESS( array( 'name' => 'initialname') );

        $this->assertTrue( $obj->set('name', 'name' ) );
        $this->assertSame( $obj->get('name') , 'name' ) ;

        $this->assertSame( $obj->unsetProperty('name'), 1 );
        $this->assertNull( $obj->get('name') );

    }

    public function test_READONLY_DISABLE()
    {

        $obj = new EBT_READONLY_DISABLE( array( 'name' => 'initialname') );

        $this->assertFalse( $obj->set('name', 'name' ) );
        $this->assertSame( $obj->get('name') , 'initialname' ) ;

        $this->assertSame( $obj->unsetProperty('name'), 0 );
        $this->assertSame( $obj->get('name') , 'initialname' ) ;

    }

    public function test_READONLY_EXCEPTION()
    {

        $obj = new EBT_READONLY_EXCEPTION( array( 'name' => 'initialname') );

        $this->setExpectedException('Bond\\Entity\\Exception\\ReadonlyException');
        $obj->set('name', 'name' );

    }

    // set direct
    public function testSetDirect()
    {

        $obj = new EBT();

        $this->assertFalse( $obj->set('id','value') );
        $this->assertSame( $obj->get('id'), null );
        $this->assertSame( $obj->setDirect('id', 'value'), 1 );
        $this->assertSame( $obj->get('id'), 'value');

    }

    public function testSetDirectByArray()
    {

        $obj = new EBT();

        $data = array('id'=>222, 'name'=> 222);

        $this->assertSame( $obj->setDirect( array() ), 0 );
        $this->assertSame( $obj->setDirect( $data ), 2 );
        $this->assertSame( $obj->data, $data );

    }

    // key get
    public function testKeyGet()
    {

        $obj1 = new EBT();
        $this->assertNull( $obj1->keyGet( $obj1 ) );
        $this->assertSame( $obj1->keyGet( array( 'id' => 1 ) ), '1' );

        $obj2 = new EBT(array('id'=>1));
        $this->assertSame( $obj1->keyGet( $obj2), '1' );

        $this->assertNull( EBT::keyGet( array() ) );

    }

    public function testKeyGetFailure()
    {
        $this->setExpectedException("Bond\\Entity\\Exception\\BadKeyException");
        $this->assertNull( EBT::keyGet( null ) );
    }

    // get
    public function testGet()
    {

        $obj = new EBT( array('id'=>222, 'name'=> 222) );

        $this->assertSame( $obj->get('id'), 222 );
        $this->assertSame( $obj->get('name'), 222 );

    }

    public function testGetByArray()
    {

        $result = array('id'=>222, 'name'=> 222);

        $obj = new EBT( $result );

        $this->assertSame( $obj->get( array_keys( $result ) ), $result );

    }

    public function testGetBadThings()
    {

        $obj = new EBT( array('id'=>222, 'name'=> 222) );

        $this->setExpectedException('Bond\\Entity\\Exception\\BadPropertyException');
        $this->assertNull( $obj->get('this IS NOT a VALID property') );

    }

    public function testGetBadThingsSuppressed()
    {

        $obj = new EBT( array('id'=>222, 'name'=> 222) );
        $this->assertNull( $obj->get('this IS NOT a VALID property', Base::VALIDATE_STRIP ) );
        $this->assertNull( $obj->get('this IS NOT a VALID property', Base::VALIDATE_DISABLE ) );

    }

    public function testCustomProperty()
    {

        $obj = new EBT();
        $obj->set('somenewproperty','somevalue', Base::VALIDATE_DISABLE );

        $this->assertSame( $obj->get('somenewproperty'), 'somevalue' );
        $this->assertTrue( $obj->issetProperty('somenewproperty') );
        $this->assertTrue( $obj->hasProperty('somenewproperty') );

    }

    // unset
    public function testUnset()
    {

        $obj = new EBT( array('id'=>222, 'name'=> 222) );

        $this->assertSame( $obj->unsetProperty('this IS NOT a VALID property' ), 0 );
        $this->assertSame( $obj->unsetProperty('id'), 0 );
        $this->assertSame( $obj->get('id'), 222 );

        $this->assertSame( $obj->unsetProperty('name'), 1 );
        $this->assertSame( $obj->get('name'), null );

        $this->assertSame( $obj->unsetProperty('name'), 0 );

    }

    public function testUnsetAsArray()
    {

        $obj = new EBT( array('id'=>222, 'name'=> 222) );

        $this->assertSame( $obj->unsetProperty( array( 'this IS NOT a VALID property', 'id', 'name' ) ), 1 );
        $this->assertSame( $obj->get('name'), null );

    }

    // isset
    public function testIsset()
    {

        $obj = new EBT( array('id'=>222, 'name'=> 222) );

        $this->assertTrue( $obj->issetProperty('name') );
        $this->assertTrue( $obj->issetProperty('id') );

    }

    public function testIssetWithLateLoadProperty()
    {

        $obj = new EBT(123);

        $this->assertTrue( $obj->issetProperty('id') );
        $this->assertFalse( $obj->isLoaded() );

    }

    // __get()
    public function test__get()
    {

        $data = array('id'=>222, 'name'=> 222);
        $obj = new EBT($data);

        $this->assertSame( $obj->keys, array_keys( $data ) );
        $this->assertSame( $obj->data, $data );

    }

    public function testIterable()
    {

        $obj = new EBT( array('id'=>123, 'name'=> 'name') );

        $keys = array();
        $values = array();

        foreach( $obj as $key => $value ) {
            $keys[] = $key;
            $values[] = $value;
        }

        // check this corresponds to the data array
        $this->assertSame( $obj->data, array_combine( $keys, $values ) );

    }

    public function testArrayAccess()
    {

        $data = array('id'=>123, 'name'=> 'name');
        $obj = new EBT($data);

        $this->assertSame( $obj['id'], $data['id'] );

        unset( $obj['id'] );
        $this->assertSame( $obj['id'], $data['id'] );

        unset( $obj['name'] );
        $this->assertNull( $obj['name'] );

        $obj['name'] = 'spanner';
        $this->assertSame( $obj['name'], 'spanner' );

        $obj[] = 'spanner';
        $this->assertSame( count( $obj->data ), 2 );

    }

    public function testArrayAccess_getBadArgument()
    {

        $obj = new EBT();
        $this->setExpectedException("Bond\\Entity\\Exception\\BadPropertyException");
        $property = $obj['badproperty'];

    }

    public function testArrayAccess_setBadArgument()
    {

        $obj = new EBT();
        $this->setExpectedException("Bond\\Entity\\Exception\\BadPropertyException");
        $obj['badproperty'] = 'spanner';

    }

    public function testCountable()
    {

        $data = array('id'=>123, 'name'=> 'name');
        $obj = new EBT($data);

        $this->assertSame( count( $obj ), count( $obj->data ) );

    }

    public function testNewObjectIsAlwaysChanged()
    {

        $obj = new EBT();
        $this->assertTrue( $obj->isChanged() );

        $obj->set('name','spanner');
        $this->assertTrue( $obj->isChanged() );

        $obj->set('name', null );
        $this->assertTrue( $obj->isChanged() );

    }

    public function testLateLoadedObjectsAreNotChanged()
    {

        $obj = new EBT(123);
        $this->assertFalse( $obj->isChanged() );

    }

    public function testHasProperty()
    {
        $obj = new EBT();
        $this->assertFalse( $obj->hasProperty('monkeynuts') );
        $this->assertTrue( $obj->hasProperty('name') );
    }

    public function testStaticPropertyDefaults()
    {
        $obj = new EBT();

        $this->assertSame( $obj->unsetableProperties, array('id') );
        $this->assertSame( $obj->lateLoadProperty, 'id' );
        $this->assertSame( $obj->lateLoad, Base::LOAD_DATA_LATE );
        $this->assertSame( $obj->isReadOnly, Base::FULL_ACCESS );
        $this->assertSame( $obj->inputValidate, Base::VALIDATE_EXCEPTION );
    }

    public function test__getNamespace()
    {
        $obj = new EBT();
        $this->assertSame( $obj->namespace, 'Bond\Entity\Tests\TestEntities' );

        $obj = new EBT_READONLY_DISABLE();
        $this->assertSame( $obj->namespace, 'Bond\Entity\Tests' );
    }

    public function testIsReadonly()
    {
        $this->assertSame( EBT::isReadonly(), Base::FULL_ACCESS );
    }

//    public function testMarkPersisted()
//    {
//
//        $obj = new EBT();
//        $this->assertTrue( $obj->isChanged() );
//        $this->assertNull( $obj->isNew() );
//
//        $this->assertSame( $obj->markPersisted(), $obj );
//        $this->assertFalse( $obj->isChanged() );
//        $this->assertNull( $obj->isNew() );
//
//    }
//
//    public function testMarkDeleted()
//    {
//
//        $obj = new EBT();
//        $obj->markPersisted();
//        $this->assertFalse( $obj->isChanged() );
//
//        $this->assertSame( $obj->markDeleted(), $obj );
//        $this->assertTrue( $obj->isChanged() );
//        $this->assertNull( $obj->isNew() );
//
//    }

}