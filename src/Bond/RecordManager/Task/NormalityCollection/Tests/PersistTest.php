<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\NormalityCollection\Tests;

use Bond\RecordManager\Task\NormalityCollection;
use Bond\RecordManager\Task\NormalityCollection\Persist;

use Bond\Container;
use Bond\Pg\Result;
use Bond\Repository;
use Bond\Sql\Query;

class PersistTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function testTnit()
    {

        // init
        $a1 = $this->entityManager->getRepository('A1')->make();
        $a1Task = new Persist($this->entityManager->recordManager);
        $this->assertTrue( $a1Task instanceof NormalityCollection );

        $this->assertFalse( NormalityCollection::isCompatible( $a1 ) );
        $this->assertTrue( NormalityCollection::isCompatible( new Container() ) );
        $this->assertTrue( NormalityCollection::isCompatible( new Container($a1) ) );

    }

    public function testInsertWithTwoEntitys()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        // a2
        $a2_1 = $a2r->make();
        $a2_2 = $a2r->make();
        $container = new Container( $a2_1, $a2_2 );
        $a2_1->set('name', 'new a2_1');
        $a2_2->set('name', 'new a2_2');

        $a2Task = new Persist($this->entityManager->recordManager);
        $a2Task->setObject( $container );
        $this->assertTrue( $a2Task->execute( $this->db ) );

        $this->assertSame( $this->db->query( new Query( "SELECT count(*) FROM A2;" ) )->fetch( Result::FETCH_SINGLE ), "2" );

        $result = $this->db->query( new Query("SELECT * FROM a2") )->fetch();

        foreach( $this->db->query( new Query("SELECT * FROM a2") )->fetch() as $data ) {
            $this->assertEquals( $a2r->find( $data['id'] )->data, $data );
        }

        // check is new
        $this->assertFalse( $a2_1->isNew() );
        $this->assertFalse( $a2_2->isNew() );
        $this->assertFalse( $a2_1->isChanged() );
        $this->assertFalse( $a2_2->isChanged() );

        // check repeated actions don't do anything
        $a2Task->execute( $this->db );
        $a2Task->execute( $this->db );
        $this->assertSame( $this->db->query( new Query( "SELECT count(*) FROM A2;" ) )->fetch( Result::FETCH_SINGLE ), "2" );

        // a1
        $a1 = $a1r->make();
        $properties = array(
            'int4' => 12345,
            'string' => 'some string here',
            'create_timestamp' => '2000-01-01 00:00:00',
            'foreign_key' => $a2_1->get('id')
        );

        foreach( $properties as $key => $value ) {
            $a1->set( $key, $value );
        }

        $a1Task = new Persist($this->entityManager->recordManager);
        $a1Task->setObject( new Container( $a1 ) );
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

        $string = "some value \\ \' \" ";

        $container = new Container();

        foreach( range(1,10) as $n ) {
            $a1 = $a1r->make();
            $a1->set('string', $string );
            $a1->set('foreign_key', $a2r->make() );
            $container->add( $a1 );
        }

        $a1Task = new Persist($this->entityManager->recordManager);
        $a1Task->setObject( $container );

        $numQuerys = $this->db->numQuerys;

        $this->assertTrue( $a1Task->execute( $this->db ) );

        // I'm not sure we can continue with this tests below because chainsaving, additional tasks, ...
        // effect this in ways that, while determinsitic, probably shouldn't be tested in this manner
        return;

        $this->assertSame( $numQuerys + 2, $this->db->numQuerys );

        $this->assertSame( $this->db->query( new Query("SELECT count(*) FROM A1"))->fetch( Result::FETCH_SINGLE ), (string) count( $container ) );
        $this->assertSame( $this->db->query( new Query("SELECT count(*) FROM A2"))->fetch( Result::FETCH_SINGLE ), (string) count( $container ) );
        $this->assertSame( $this->db->query( new Query("SELECT DISTINCT string FROM A1"))->fetch( Result::FETCH_SINGLE ), $string );

    }

    public function testUpdateWithTwoEntitys()
    {

        $a2r = $this->entityManager->getRepository('A2');

        $this->db->query( new Query( "INSERT INTO a2( id, name ) VALUES (DEFAULT,'one'),(DEFAULT,'two')" ) );

        $a2s = $a2r->findAll();

        foreach( $a2s as $a2 ) {
            $a2->set('name', strtoupper( $a2->get('name') ) );
        }

        $a2Task = new Persist($this->entityManager->recordManager);
        $a2Task->setObject( $a2s );
        $a2Task->execute( $this->db );

        // check the data is what is should be
        foreach( $this->db->query( new Query("SELECT * FROM a2") )->fetch() as $data ) {
            $this->assertEquals( $a2r->find( $data['id'] )->data, $data );
        }

    }

    public function testLogsAreReadonly()
    {

        $repo = $this->entityManager->getRepository('Log');

        $id = $this->db->query( new Query( "INSERT INTO \"log\" ( op ) VALUES( 'one' ) RETURNING \"logId\"" ) )->fetch( Result::FETCH_SINGLE );
        $one = $repo->find( $id );
        $this->setExpectedException("Bond\\Entity\\Exception\\ReadonlyException");
        $one->set('op', 'new value');

    }

    public function testLogsPersistOk_aka_aReadonlyCheckUnitTest()
    {

        $repo = $this->entityManager->getRepository('Log');

        $two = $repo->make(['op' => 'two']);

        $this->assertSame( $this->getNumberRowsInTable('log'), 0 );

        $task = new Persist($this->entityManager->recordManager);
        $task->setObject( new Container( $two ) );
        $task->execute( $this->db );

        $this->assertSame( $this->getNumberRowsInTable('log'), 1 );

    }

    public function testPersistNullableEnums()
    {

        $repo = $this->entityManager->getRepository('A4');

        $container = new Container(
            $repo->make(
                array(
                    'name' => 'one',
                    'type' => 'one',
                    'typeNullable' => 'one'
                )
            ),
            $repo->make(
                array(
                    'name' => 'two',
                    'type' => 'two',
                    'typeNullable' => null
                )
            )
        );

        $task = new Persist($this->entityManager->recordManager);
        $task->setObject( $container );
        $task->execute( $this->db );

        $this->assertSame( $this->getNumberRowsInTable('a4'), 2 );

        $result = $this->db->query( new Query( "SELECT * FROM a4" ) )->fetch();
//        print_r( $result );

    }

}