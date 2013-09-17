<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\Normality\Tests;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Normality;
use Bond\RecordManager\Task\Normality\Delete;

use Bond\Pg\Result;
use Bond\Sql\Query;

use Bond\Pg\Exception\States\State23503;

class DeleteTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function setup()
    {

        parent::setup();

        $sql = <<<SQL
            INSERT INTO A1 ( id, int4, string, create_timestamp, foreign_key ) VALUES ( 101, 1234, 'hi', now(), null );
            INSERT INTO A1 ( id, int4, string, create_timestamp, foreign_key ) VALUES ( 201, 1234, 'hi', now(), null );

            INSERT INTO A2 ( id, name ) VALUES ( 102, 'name 102' );
            INSERT INTO A2 ( id, name ) VALUES ( 202, 'name 202' );

            INSERT INTO A1 ( id, int4, string, create_timestamp, foreign_key ) VALUES ( 301, 1234, 'hi', now(), 102 );
SQL
;

        $this->db->query( new Query( $sql ) );

    }

    public function testDelete()
    {

        $a1r = $this->entityManager->getRepository('A1');
        $a2r = $this->entityManager->getRepository('A2');

        $a1_101 = $a1r->find( 101 );
        $a1_101->get('string');

        $task = $this->entityManager->recordManager->getTask( $a1_101, Task::DELETE );

        $this->assertTrue( $task->execute( $this->db ) );
        $this->assertSame( $this->db->query( new Query("SELECT count(*) FROM A1 WHERE id = 101") )->fetch( Result::FETCH_SINGLE ), '0' );
        $this->assertNull( $a1r->find(101,true) );

        $task = $this->entityManager->recordManager->getTask( $a2r->find(202), Task::DELETE );
        $this->assertTrue( $task->execute( $this->db ) );
        $this->assertSame( $this->db->query( new Query("SELECT count(*) FROM A2 WHERE id = 202") )->fetch( Result::FETCH_SINGLE ), '0' );

        $task = $this->entityManager->recordManager->getTask( $a2r->find(102), Task::DELETE );
        $this->setExpectedException(State23503::class);
        $task->execute( $this->db );

    }

}