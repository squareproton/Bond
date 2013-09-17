<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\Normality\Tests;

use Bond\RecordManager\Task\Normality;
use Bond\RecordManager\Task\Normality\Persist;

use Bond\Pg\Result;
use Bond\Sql\Query;

use Bond\Repository\Multiton;

class PersistTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function testEnum()
    {

        $a4r = $this->entityManager->getRepository('A4');
        $a4 = $a4r->make();

        $a4['name'] = 'enum test';
        $a4['type'] = 'one';

        $a4Task = new Persist($this->entityManager->recordManager);
        $a4Task->setObject( $a4 );
        $this->assertTrue( $a4Task->execute( $this->db ) );

    }

    public function testTnit()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A1');

        // init
        $a1 = $a1r->make();
        $a1Task = new Persist($this->entityManager->recordManager);
        $this->assertTrue( $a1Task instanceof Normality );
        $this->assertTrue( Normality::isCompatible( $a1 ) );
        $this->assertTrue( $a1Task->setObject( $a1 ) );
        $this->assertSame( $a1Task->getObject(), $a1 );

        $a2 = $a2r->make();
        $a2Task = new Persist($this->entityManager->recordManager);
        $this->assertTrue( $a2Task instanceof Normality );
        $this->assertTrue( Normality::isCompatible( $a2 ) );
        $this->assertTrue( $a2Task->setObject( $a2 ) );
        $this->assertSame( $a2Task->getObject(), $a2 );

    }

    public function testInsert1()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        // a2
        $a2 = $a2r->make();
        $a2->set('name', 'new a2');

        $a2Task = new Persist($this->entityManager->recordManager);
        $a2Task->setObject( $a2 );
        $this->assertTrue( $a2Task->execute( $this->db ) );

        $this->assertSame( $this->db->query( new Query( "SELECT count(*) FROM A2;" ) )->fetch( Result::FETCH_SINGLE ), "1" );
        $dbData = $a2r->data( $a2->get('id') );
        unset( $dbData['key'] );
        $this->assertSame( $dbData, $a2->data );

        // check repeated actions don't do anything
        $this->assertTrue( $a2Task->execute( $this->db ) );
        $this->assertTrue( $a2Task->execute( $this->db ) );
        $this->assertSame( $this->db->query( new Query( "SELECT count(*) FROM A2;" ) )->fetch( Result::FETCH_SINGLE ), "1" );

        // a1
        $a1 = $a1r->make();
        $properties = array(
            'int4' => 12345,
            'string' => 'some string here',
            'create_timestamp' => '2000-01-01 00:00:00',
            'foreign_key' => $a2->get('id')
        );

        foreach( $properties as $key => $value ) {
            $a1->set( $key, $value );
        }

        $a1Task = new Persist($this->entityManager->recordManager);
        $a1Task->setObject( $a1 );
        $this->assertTrue( $a1Task->execute( $this->db ) );

        // tests
        $this->assertSame( $this->db->query( new Query( "SELECT count(*) FROM A1;" ) )->fetch( Result::FETCH_SINGLE ), "1" );

        $dbData = $this->db->query( new Query("SELECT * FROM A1;"))->fetch( Result::FETCH_SINGLE );
        $this->assertEquals( array_intersect_key( $properties, $dbData ), $properties );

    }

    public function testInsert2()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        // a1
        $a1 = $a1r->make();
        $a1->set('string','some value');
        $a1->set( 'foreign_key', $a2r->make() );

        $a1Task = new Persist($this->entityManager->recordManager);
        $a1Task->setObject( $a1 );
        $a1Task->execute( $this->db );

        $this->assertSame( $this->db->query( new Query("SELECT count(*) FROM A1"))->fetch( Result::FETCH_SINGLE ), "1" );
        $this->assertSame( $this->db->query( new Query("SELECT count(*) FROM A2"))->fetch( Result::FETCH_SINGLE ), "1" );
        $this->assertSame( $this->db->query( new Query("SELECT string FROM A1 ORDER BY id DESC LIMIT 1"))->fetch( Result::FETCH_SINGLE ), "some value" );

    }

    public function testUpdate()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        // a1
        $a1 = $a1r->make();
        $a2 = $a2r->make();
        $a1->set( 'foreign_key', $a2 );

        $a1Task = new Persist($this->entityManager->recordManager);
        $a1Task->setObject( $a1 );
        $a1Task->execute( $this->db );

        // a1
        $this->assertFalse( $a1r->isNew( $a1 ) );
        $this->assertFalse( $a1->isChanged() );

        // a2 (chain saving)
        $this->assertFalse( $a2r->isNew( $a2 ) );
        $this->assertFalse( $a2->isChanged() );

        $a1->set('foreign_key', null );

        $this->assertFalse( $a1->isNew() );
        $this->assertTrue( $a1->isChanged() );

        $a1Task->execute( $this->db );
        $this->assertFalse( $a1->isChanged() );

    }

    public function testMakeLink()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a4r = $this->entityManager->getRepository('A4');
        $a1linka4r = $this->entityManager->getRepository('A1linkA4');

        $this->db->query( new Query( <<<SQL
            INSERT INTO a1( id, int4, string ) values ( 1, 1, 'one' );
            INSERT INTO a4( id, name, type ) values( 1, 'one', 'one' );
SQL
        ));

        $a1 = $a1r->find(1);
        $a4 = $a4r->find(1);

        $a1linka4 = $a1linka4r->make();
        $a1linka4->set('A1', $a1);
        $a1linka4->set('A4', $a4);

        $this->assertSame( $a1linka4r->cacheSize( Multiton::UNPERSISTED), 1 );
        $this->assertSame( $a1linka4r->cacheSize( Multiton::PERSISTED), 0 );

        $t1 = new Persist($this->entityManager->recordManager);
        $t1->setObject( $a1linka4 );
        $t1->execute( $this->db );

        $this->assertSame( static::getNumberRowsInTable('a1_link_a4'), 1 );
        $this->assertSame( $a1linka4r->cacheSize( Multiton::UNPERSISTED), 0 );
        $this->assertSame( $a1linka4r->cacheSize( Multiton::PERSISTED), 1 );

        $this->assertSame( $a1linka4, $a1linka4r->find('1|1') );

    }

    public function testLinksMakeSavingViaLink()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a4r = $this->entityManager->getRepository('A4');
        $a1linka4r = $this->entityManager->getRepository('A1linkA4');

        // a2
        $a1 = $a1r->make();
        $a4 = $a4r->make();
        $a4->set('type','one');

        $a1linka4 = $a1linka4r->make();
        $a1linka4->set('A1', $a1 );
        $a1linka4->set('A4', $a4 );

        $t1 = new Persist($this->entityManager->recordManager);
        $t1->setObject( $a1linka4 );

        $t1->execute( $this->db );

        // got three objects in the database
        $this->assertSame( static::getNumberRowsInTable('a1'), 1 );
        $this->assertSame( static::getNumberRowsInTable('a4'), 1 );
        $this->assertSame( static::getNumberRowsInTable('a1_link_a4'), 1 );

        $this->assertSame( $a1r->find( $a1->keyGet( $a1 ) ), $a1 );

        $this->assertSame( $a4r->find( $a4->keyGet( $a4 ) ), $a4 );
        $this->assertSame( $a1linka4r->find( $a1linka4->keyGet( $a1linka4 ) ), $a1linka4 );

    }

    public function testRecursion()
    {

        $r1r = $this->entityManager->getRepository('R1');

        $r = $r1r->make();

        $r->set('links', $r );
        $r->set('name', 'recurse' );

        $t1 = new Persist($this->entityManager->recordManager);
        $t1->setObject( $r );
        $t1->execute( $this->db );

        $this->assertFalse( $r->isNew() );
        $this->assertFalse( $r->isChanged() );
        $this->assertSame( $r, $r['links'] );

        $r1r->cacheInvalidate();
        $key = $r->keyGet( $r );
        unset( $r );

        $r = $r1r->find( $key );

        $this->assertSame( $r['links'], $r );

    }

    public function testObjectUpdatePrimaryKey()
    {

        list( $a1_1, $a4, $a1linka4 ) = $this->build_newa1linka4Triplet();

        $a1_2 = $this->entityManager->getRepository('A1')->make();
        $a1linka4['a1_id'] = $a1_2;
        $this->assertSame( $a1linka4->get('a1_id', null, $_ = $a1linka4::DATA ),   $a1_2 );
        $this->assertSame( $a1linka4->get('a1_id', null, $_ = $a1linka4::INITIAL ), $a1_1 );

        $this->assertSame( $this->getNumberRowsInTable('a1_link_a4'), 1 );

        $t2 = new Persist($this->entityManager->recordManager);
        $t2->setObject( $a1linka4 );
        $t2->execute( $this->db );

        $this->assertSame( $a1linka4->get('a1_id', null, $_ = $a1linka4::INITIAL ), $a1_2 );

        $this->assertSame( $this->getNumberRowsInTable('a1_link_a4'), 1 );

        $row = $this->db->query( new Query( "SELECT * FROM a1_link_a4" ) )->fetch();
        $this->assertSame( $a1linka4['a1_id']['id'], $row[0]['a1_id'] );

    }

    public function testObjectReferences()
    {

        $a1 = $this->entityManager->getRepository('A1')->make();
        $a1['string'] = 'a1';

        $a11 = $this->entityManager->getRepository('A11')->make();
        $a11['A1'] = $a1;
        $a11['name'] = 'a11';

        $t1 = new Persist($this->entityManager->recordManager);
        $t1->setObject( $a1 );
        $t1->execute( $this->db );

        $this->assertSame( $this->getNumberRowsInTable('a1'), 1 );
        $this->assertSame( $this->getNumberRowsInTable('a11'), 1 );

        $a1['A11'] = null;

        $t1 = new Persist($this->entityManager->recordManager);
        $t1->setObject( $a1 );
        $t1->execute( $this->db );

    }

    public function build_newa1linka4Triplet()
    {

        $a1 = $this->entityManager->getRepository('A1')->make();
        $a4 = $this->entityManager->getRepository('A4')->make(array('type'=>'one'));

        $a1linka4 = $this->entityManager->getRepository('A1linkA4')->make(
            array(
                'a1_id' => $a1,
                'a4_id' => $a4,
            )
        );

        $t1 = new Persist($this->entityManager->recordManager);
        $t1->setObject( $a1linka4 );

        try {
            $t1->execute($this->db);
        } catch ( \Exception $e ) {
            print_r( $e->sql );
            print_r( $e->error );
//            die();
        }

        return array( $a1, $a4, $a1linka4 );

    }

}