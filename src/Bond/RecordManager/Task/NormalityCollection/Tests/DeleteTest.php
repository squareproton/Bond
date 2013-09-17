<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\NormalityCollection\Tests;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Normality;
use Bond\RecordManager\Task\Normality\Delete;

use Bond\Pg\Result;
use Bond\Sql\Raw;

use Bond\Container;

use Bond\Pg\Exception\States\State23503;

class DeleteTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function setup()
    {

        parent::setup();

        $sql = <<<SQL
            INSERT INTO a1 ( id, int4, string, create_timestamp, foreign_key ) VALUES ( 101, 1234, 'hi', now(), null );
            INSERT INTO a1 ( id, int4, string, create_timestamp, foreign_key ) VALUES ( 201, 1234, 'hi', now(), null );

            INSERT INTO a2 ( id, name ) VALUES ( 102, 'name 102' );
            INSERT INTO a2 ( id, name ) VALUES ( 202, 'name 202' );

            INSERT INTO a1 ( id, int4, string, create_timestamp, foreign_key ) VALUES ( 301, 1234, 'hi', now(), 102 );

            INSERT INTO a4 ( id, name, type ) VALUES ( 101, '101', 'one' );
            INSERT INTO a4 ( id, name, type ) VALUES ( 201, '201', 'one' );

            INSERT INTO a1_link_a4( a1_id, a4_id ) VALUES( 101, 101 );

SQL
;

        $this->db->query( new Raw( $sql ) );

    }

    public function testDelete()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        $task = $this->entityManager->recordManager->getTask( new Container( $a1r->find( 101 ) ), Task::DELETE );
        $this->assertTrue( $task->execute( $this->db ) );

        $this->assertSame( $this->db->query( new Raw("SELECT count(*) FROM A1 WHERE id = 101") )->fetch( Result::FETCH_SINGLE ), '0' );
        $this->assertNull( $a1r->find(101,true) );

        $task = $this->entityManager->recordManager->getTask( new Container( $a2r->find(202) ), Task::DELETE );
        $this->assertTrue( $task->execute( $this->db ) );
        $this->assertSame( $this->db->query( new Raw("SELECT count(*) FROM A2 WHERE id = 202") )->fetch( Result::FETCH_SINGLE ), '0' );

        $task = $this->entityManager->recordManager->getTask( new Container( $a2r->find(102) ), Task::DELETE );
        $this->setExpectedException(State23503::class);
        $task->execute( $this->db );

    }

    public function testDeleteLinks()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');
        $this->db = $this->db;

        $result = $this->db->query(new Raw("SELECT * FROM a1_link_a4"))->fetch();
        $this->assertSame( $this->db->query( new Raw("SELECT count(*) FROM a1_link_a4 WHERE a1_id = 101 AND a4_id = 101") )->fetch( Result::FETCH_SINGLE ), '1' );

        $a1 = $a1r->find(101);
        $links = $a1->get('A1linkA4s');

        $task = $this->entityManager->recordManager->getTask( $links, Task::DELETE );
        $task->execute( $this->db );

        $this->assertSame( $this->db->query( new Raw("SELECT count(*) FROM a1_link_a4 WHERE a1_id = 101 AND a4_id = 101") )->fetch( Result::FETCH_SINGLE ), '0' );

    }

}