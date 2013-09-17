<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Tests;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Base;
use Bond\RecordManager\Task\Normality;
use Bond\RecordManager\Task\Normality\Delete;
use Bond\RecordManager\Task\Normality\Persist;
use Bond\RecordManager\Task\NormalityCollection;
use Bond\RecordManager\Task\PgLargeObject\Delete as LargeObjectTaskDelete;
use Bond\RecordManager\Task\PgLargeObject\Persist as LargeObjectTaskPersist;
use Bond\RecordManager\Task\Query;

use Bond\Sql\Query as PgQuery;
use Bond\Entity\Types\PgLargeObject as LargeObject;
use Bond\Entity\Types\Oid;

use Bond\Container;
use \stdClass;

/**
 * Stub object that'll trick Normality into accepting this object
 */
class normalityStub
{
    function isNew(){}
    function isZombie(){}
    function isChanged(){}
    function isReadonly(){}
    function checksumReset(){}
    function setDirect(){}
    function r(){}
}

class TaskTest extends \Bond\Tests\EntityManagerProvider
{

    public function testInitNonObject()
    {

        $base = new Base( $this->entityManager->recordManager );

        $this->assertTrue( $base instanceof Base );
        $this->assertTrue( $base instanceof Task );

        $this->assertNull( $base->getObject() );

        $this->setExpectedException("Bond\\RecordManager\\Exception\\BadTaskException");
        $base->setObject(123, false);

    }

    public function testInitIncompatibleObject()
    {

        $base = new Query( $this->entityManager->recordManager );
        $this->assertFalse( $base->setObject( new stdClass(), false ) );
        $this->assertNull( $base->getObject() );

        $this->setExpectedException("Bond\\RecordManager\\Exception\\BadTaskException");
        $base->setObject( new stdClass() );

    }

    public function testBase1()
    {

        $base = new Base( $this->entityManager->recordManager );

        $obj = new stdClass();
        $this->assertTrue( $base->setObject( $obj, false) );
        $this->assertSame( $base->getObject(), $obj );

    }

    public function testInitBaseWithBadOperation()
    {
        $this->setExpectedException("Bond\\RecordManager\\Exception\\BadOperationException");
        $base = $this->entityManager->recordManager->getTask( new stdClass(), 'not a valid operation' );
    }

    public function testInitBaseWithNonObject()
    {
        $this->setExpectedException("Bond\\RecordManager\\Exception\\BadTaskException");
        $this->entityManager->recordManager->getTask( 123, Task::PERSIST );
    }

    public function testInitNormality1()
    {
        $obj = new stdClass();
        $this->assertNull( $this->entityManager->recordManager->getTask($obj, Task::PERSIST, false ) );
        $this->setExpectedException("Bond\\RecordManager\\Exception\\BadTaskException");
        $task = $this->entityManager->recordManager->getTask($obj, Task::PERSIST );
    }

    public function testInitNormality2()
    {
        $obj = new normalityStub();
        $task = $this->entityManager->recordManager->getTask($obj, Task::PERSIST );
        $this->assertTrue( $task instanceof Normality );
    }

    public function testInitEntityContainer()
    {
        $obj = new Container();
        $task = $this->entityManager->recordManager->getTask( $obj, Task::PERSIST );
        $this->assertTrue( $task instanceof NormalityCollection );
    }

    public function testInitQuery()
    {
        $obj = new PgQuery("SELECT 1;");
        $task = $this->entityManager->recordManager->getTask($obj, Task::PERSIST);
        $this->assertTrue( $task instanceof Query );
    }

    public function testInitPgLargeObjectPersist()
    {
        $obj = new LargeObject( new Oid( 1234, $this->entityManager->db ) );

        $task = $this->entityManager->recordManager->getTask($obj, Task::PERSIST);
        $this->assertTrue( $task instanceof LargeObjectTaskPersist );

        $task = $this->entityManager->recordManager->getTask($obj, Task::DELETE);
        $this->assertTrue( $task instanceof LargeObjectTaskDelete );

    }

}