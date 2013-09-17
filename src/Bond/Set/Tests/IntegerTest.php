<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Set\Tests;

use Bond\Set;
use Bond\Set\Integer;

class SetIntegerTest extends \PHPUnit_Framework_Testcase
{

    public function testConstructWithString()
    {

        $set = new Integer();
        $this->assertSame( (string) $set, '' );

        $set = new Integer('6');
        $this->assertSame( (string) $set, '6' );

        $set = new Integer('6-8');
        $this->assertSame( (string) $set, '6-8' );

        $set = new Integer('-9');
        $this->assertSame( (string) $set, '-9' );

        $set = new Integer('9-');
        $this->assertSame( (string) $set, '9-' );

        $set = new Integer('1,2,3');
        $this->assertSame( (string) $set, '1-3' );

        $set = new Integer('1,,');
        $this->assertSame( (string) $set, '1' );

        $set = new Integer('1,1,1');
        $this->assertSame( (string) $set, '1' );

        $set = new Integer('1-2,3-4');
        $this->assertSame( (string) $set, '1-4' );

        $set = new Integer('9-1');
        $this->assertSame( (string) $set, '1-9' );

        $set = new Integer('-9,5-');
        $this->assertSame( (string) $set, '-' );

        $set = new Integer('-3,4-10,11-');
        $this->assertSame( (string) $set, '-' );

        $set = new Integer('-1, 5, 5-');
        $this->assertSame( (string) $set, '-1,5-' );

        $set = new Integer('-,1-10');
        $this->assertSame( (string) $set, '-' );

        $set = new Integer('1-10,5-6');
        $this->assertSame( (string) $set, '1-10' );

        $set = new Integer('6a');
        $this->assertSame( (string) $set, '6' );

        $set = new Integer('a');
        $this->assertSame( (string) $set, '' );

        $set = new Integer('');
        $this->assertSame( (string) $set, '' );

        $set = new Integer('1-2-3');
        $this->assertSame( (string) $set, '' );

        $set = new Integer('\\-1-2');
        $this->assertSame( (string) $set, '\\-1-2' );

        $set = new Integer('2-\\-1');
        $this->assertSame( (string) $set, '\\-1-2' );

        $set = new Integer('\\-3');
        $this->assertSame( (string) $set, '\\-3' );

        $set = new Integer('1.0');
        $this->assertSame( (string) $set, '1' );

        $set = new Integer(-10);
        $this->assertSame( (string) $set, '\\-10' );

        $set = new Integer(1,3,5);
        $this->assertSame( (string) $set, '1,3,5' );

    }

    public function testConstructWithMultipleArguments()
    {

        $set = new Integer(1,2,3);
        $this->assertSame( (string) $set, '1-3' );

        $set = new Integer(1,null,3);
        $this->assertSame( (string) $set, '\\0,1,3' );

        $set = new Integer( 1, new Integer(5), array(6), array('7'), 8 );
        $this->assertSame( (string) $set, '1,5-8' );

    }

    public function testIntegerConstructWithArray()
    {

        $set = new Integer( array(1) );
        $this->assertSame( (string) $set, '1' );

        $set = new Integer( array(1, 'spanner') );
        $this->assertSame( (string) $set, '1' );

        $set = new Integer( array(null, null) );
        $this->assertSame( (string) $set, '\\0' );

        $set = new Integer( range(1,100) );
        $this->assertSame( (string) $set, '1-100' );

        $range = range(1, 100);
        shuffle( $range );
        $set = new Integer( $range );
        $this->assertSame( (string) $set, '1-100' );

    }

    public function testIntegerConstructWithSet()
    {

        $set_a = new Integer( range(1,100) );
        $set_b = new Integer( $set_a );

        $this->assertSame( (string) $set_a, (string) $set_b );

    }

    public function testIntegerConstructWithNull()
    {

        $set = new Integer('\\0');
        $this->assertSame( (string) $set, '\\0' );

        $set = new Integer(null);
        $this->assertSame( (string) $set, '\\0' );

        $set = new Integer(array(null));
        $this->assertSame( (string) $set, '\\0' );

        $set = new Integer('\\01-10');
        $this->assertSame( (string) $set, '1-10' );

        $set = new Integer('1\\0-10');
        $this->assertSame( (string) $set, '1-10' );

    }

    public function testCount()
    {

        $this->assertEquals( count( new Integer('') ), 0 );
        $this->assertEquals( count( new Integer('6') ), 1 );
        $this->assertEquals( count( new Integer('1-5') ), 5 );
        $this->assertEquals( count( new Integer('1,5,7') ), 3 );

        // php's count always casts to a integer so we can't actually have a representation of a uncountable set
        $this->assertEquals( count( new Integer('-100') ), 0 );

        $set = new Integer('-100');
        $this->assertNull( $set->count() );

        $set = new Integer();
        $this->assertEquals( $set->count(), 0 );
        $this->assertEquals( $set->add(null)->count(), 1 );

    }

    public function testIsEmpty()
    {

        $set = new Integer();
        $this->assertTrue( $set->isEmpty() );

        $set = new Integer('');
        $this->assertTrue( $set->isEmpty() );

        $set = new Integer('6');
        $this->assertFalse( $set->isEmpty() );

    }

    // this isn't tested tested very much because a lot of testing has been done via the constructor tests
    public function testAdd()
    {

        $set = new Integer(1);
        $set->add(2);
        $this->assertSame( (string) $set, '1-2' );

        $set = new Integer(1);
        $set->add(1);
        $this->assertSame( (string) $set, '1' );

        $set = new Integer(1);
        $set->add('10-');
        $this->assertSame( (string) $set, '1,10-' );

        $set = new Integer('-');
        $set->add('10');
        $this->assertSame( (string) $set, '-' );

        // chaining
        $this->assertSame( $set->add(6), $set );
        $this->assertSame( $set->add(), $set );

    }

    public function testRemove()
    {

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove('1-10'), '' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove('1-100'), '' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove('0-10'), '' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove('0-11'), '' );

        $set->remove('1-10');
        $this->assertSame( (string) $set->remove('-'), '' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove('-10'), '' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove('1-'), '' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove(5), '1-4,6-10' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove(1), '2-10' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove(10), '1-9' );

        $set = new Integer('1-10');
        $this->assertSame( (string) $set->remove(10,8,6,9), '1-5,7' );

        $set = new Integer('1-3,5-6,10-');
        $this->assertSame( (string) $set->remove('3-5,15'), '1-2,6,10-14,16-' );

        $set = new Integer('-');
        $this->assertSame( (string) $set->remove('5'), '-4,6-' );

        $set = new Integer('-');
        $this->assertSame( (string) $set->remove('-5'), '6-' );

        $set = new Integer('-');
        $this->assertSame( (string) $set->remove('5-'), '-4' );

        $set = new Integer('-');
        $this->assertSame( (string) $set->remove('-0'), '1-' );

        // chaining
        $this->assertSame( $set, $set->remove() );
        $this->assertSame( $set, $set->remove('-') );

    }

    public function testContains()
    {

        $set = new Integer('1-10');
        $this->assertTrue( $set->contains(1,10) );

        $set = new Integer('1-10');
        $this->assertTrue( $set->contains('1-10') );

        $set = new Integer('1-10');
        $this->assertTrue( $set->contains('5-7') );

        $set = new Integer('1-10');
        $this->assertTrue( $set->contains( $set ) );

        $set = new Integer('1-10');
        $this->assertTrue( $set->contains() );

        $set = new Integer('1-10');
        $this->assertTrue( $set->contains('2-4','7-8') );

        $set = new Integer('1-5,7-100,1000-');
        $this->assertTrue( $set->contains('2-4','7-8') );

        $set = new Integer('1-10');
        $this->assertFalse( $set->contains(0) );

        $set = new Integer('1-10');
        $this->assertFalse( $set->contains(11) );

        $set = new Integer('1-10');
        $this->assertFalse( $set->contains('-10') );

        $set = new Integer('1-10');
        $this->assertFalse( $set->contains( '1-' ) );

        $set = new Integer('1-10');
        $this->assertFalse( $set->contains('5-15') );

        $set = new Integer('1-10');
        $this->assertFalse( $set->contains( '\\-5-5' ) );

        $set = new Integer('1-10');
        $this->assertFalse( $set->contains(5,11) );

        $set = new Integer('-10');
        $this->assertTrue( $set->contains( -100 ) );

        $set = new Integer('-10');
        $this->assertTrue( $set->contains( 10 ) );

        $set = new Integer('-10');
        $this->assertFalse( $set->contains( 11 ) );

        $set = new Integer('-10');
        $this->assertFalse( $set->contains( '11-' ) );

        $set = new Integer('10-');
        $this->assertTrue( $set->contains( 100 ) );

        $set = new Integer('10-');
        $this->assertTrue( $set->contains( 10 ) );

        $set = new Integer('10-');
        $this->assertFalse( $set->contains( 9 ) );

        $set = new Integer('10-');
        $this->assertFalse( $set->contains( '-9' ) );

        $set = new Integer('-');
        $this->assertTrue( $set->contains( '1' ) );

        $set = new Integer('-');
        $this->assertTrue( $set->contains( '1-10' ) );

        $set = new Integer('-');
        $this->assertTrue( $set->contains( '-1' ) );

        $set = new Integer('-');
        $this->assertTrue( $set->contains( '1-' ) );

        $set = new Integer('-');
        $this->assertTrue( $set->contains( '-' ) );

    }

    public function testSetAllNone()
    {

        $set = new Integer('\\0,-');
        $this->assertTrue( $set->contains(1,null) );

        $this->assertSame( $set->none(), $set );
        $this->assertFalse( $set->contains(1) );
        $this->assertFalse( $set->contains(null) );
        $this->assertSame( count( $set ), 0 );

        $this->assertSame( $set->all(), $set );
        $this->assertTrue( $set->contains('-') );
        $this->assertTrue( $set->contains(null) );

    }

    public function testInvert()
    {

        $set = new Integer(5);
        $this->assertSame( (string) $set->invert(), '\\0,-4,6-' );

        $set = new Integer('1,3');
        $this->assertSame( (string) $set->invert(), '\\0,-0,2,4-' );

        $set = new Integer('-');
        $this->assertSame( (string) $set->invert(), '\\0' );

        $set = new Integer('\\0');
        $this->assertSame( (string) $set->invert(), '-' );

        $set = new Integer('');
        $this->assertSame( (string) $set->invert(), '\\0,-' );
        $this->assertSame( (string) $set->invert(), '' );

        $set = new Integer('6-');
        $this->assertSame( (string) $set->invert(), '\\0,-5' );

        $set = new Integer('-6');
        $this->assertSame( (string) $set->invert(), '\\0,7-' );

    }

}