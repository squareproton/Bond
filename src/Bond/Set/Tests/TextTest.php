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
use Bond\Set\Text;

class SetTextTest extends \PHPUnit_Framework_Testcase
{

    public function testConstructWithString()
    {

        $set = new Text();
        $this->assertSame( (string) $set, '' );

        $set = new Text('6');
        $this->assertSame( (string) $set, '6' );

        $filthyString = '"\$%\\-:9';
        $set = new Text( Set::escape( $filthyString ) );
        $this->assertSame( $set->intervals[0][0], $filthyString );
        $this->assertTrue( $set->contains( array( $filthyString ) ) );

        $set = new Text('-B');
        $this->assertSame( (string) $set, '-B' );

        $set = new Text('9-');
        $this->assertSame( (string) $set, '9-' );

        $set = new Text('1,2,3');
        $this->assertSame( (string) $set, '1,2,3' );

        $set = new Text('1,,');
        $this->assertSame( (string) $set, '1' );

        $set = new Text('1,1,1');
        $this->assertSame( (string) $set, '1' );

        $set = new Text('A-Z');
        $this->assertSame( (string) $set, 'A-Z' );

    }

    public function testConstructWithMultipleArguments()
    {

        $set = new Text(1,null,3);
        $this->assertSame( (string) $set, '\0,1,3' );

        $set = new Text( 1, new Text(5), array(6), array('7') );
        $this->assertSame( (string) $set, '1,5,6,7' );

    }

    public function testCount()
    {

        $this->assertEquals( count( new Text('') ), 0 );
        $this->assertEquals( count( new Text('6') ), 1 );
        $this->assertEquals( count( new Text('1-5') ), 0 );
        $this->assertEquals( count( new Text('1,5,7') ), 3 );

        // php's count always casts to a integer so we can't actually have a representation of a uncountable set
        $this->assertEquals( count( new Text('-100') ), 0 );

        $set = new Text('-100');
        $this->assertNull( $set->count() );

        $set = new Text('1-5');
        $this->assertNull( $set->count() );

    }

    public function testIsEmpty()
    {

        $set = new Text();
        $this->assertTrue( $set->isEmpty() );

        $set = new Text('');
        $this->assertTrue( $set->isEmpty() );

        $set = new Text('A');
        $this->assertFalse( $set->isEmpty() );

    }

    // this isn't tested tested very much because a lot of testing has been done via the constructor tests
    public function testAdd()
    {

        $set = new Text('A');
        $set->add('B');
        $this->assertSame( (string) $set, 'A,B' );

        $set = new Text('A');
        $set->add('A');
        $this->assertSame( (string) $set, 'A' );

        $set = new Text('A');
        $set->add('B-');
        $this->assertSame( (string) $set, 'A,B-' );

        $set = new Text('-');
        $set->add('F');
        $this->assertSame( (string) $set, '-' );

    }

    public function testRemove()
    {

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('A-J'), '' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('C-E'), 'B-C,E-G' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('E'), 'B-G' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('-C'), 'C-G' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('B-D'), 'D-G' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('D-'), 'B-D' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('D-'), 'B-D' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('D-G'), 'B-D' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('D-G'), 'B-D' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('B-G'), '' );

        $set = new Text('B-G');
        $this->assertSame( (string) $set->remove('-'), '' );

    }

    public function testContains()
    {

        $set = new Text('A,B,C');
        $this->assertTrue( $set->contains('A','B','C') );

        $set = new Text('A-Z');
        $this->assertTrue( $set->contains('A-Z') );

        $set = new Text('A-Z');
        $this->assertTrue( $set->contains('B-C') );

        $set = new Text('A-Z');
        $this->assertTrue( $set->contains('Cat') );

        $set = new Text('A-Z');
        $this->assertTrue( $set->contains( $set ) );

        $set = new Text('A-Z');
        $this->assertTrue( $set->contains() );

        $set = new Text('A-Z');
        $this->assertTrue( $set->contains('Cat', 'Mat', 'Sat', 'The' ) );

        $set = new Text('A-Z');
        $this->assertFalse( $set->contains('cat', 'mat', 'sat', 'the' ) );

        $set = new Text('A-Z');
        $this->assertFalse( $set->contains( '-' ) );

    }

    public function testSetAllNone()
    {

        $set = new Text('-');
        $this->assertTrue( $set->contains(1) );

        $this->assertSame( $set->none(), $set );
        $this->assertFalse( $set->contains(1) );
        $this->assertSame( count( $set ), 0 );

        $this->assertSame( $set->all(), $set );
        $this->assertTrue( $set->contains('-') );

    }

    public function testInvertWithValues()
    {

        $this->setExpectedException("RuntimeException");

        $set = new Text(5);
        $this->assertSame( (string) $set->invert(), '-4,6-' );

    }

    public function testInvertWithRanges()
    {

        $set = new Text('\\0,-');
        $this->assertSame( (string) $set->invert(), '' );

        $set = new Text('');
        $this->assertSame( (string) $set->invert(), '\\0,-' );

        $set = new Text('A-');
        $this->assertSame( (string) $set->invert(), '\\0,-A' );

        $set = new Text('-B');
        $this->assertSame( (string) $set->invert(), '\\0,B-' );

    }

}