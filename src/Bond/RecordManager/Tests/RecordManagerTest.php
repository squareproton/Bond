<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Tests;

use Bond\Pg\Result;

use Bond\RecordManager;
use Bond\RecordManager\Task\Base;

use Bond\Sql\Query;

class RecordManagerTest extends \Bond\Normality\Tests\NormalityProvider
{

    public function testGetTransaction1()
    {

        $rm = $this->entityManager->recordManager;

        $this->assertSame( $rm->getTransaction( RecordManager::TRANSACTIONS_ALL ), [] );
        $this->assertSame( $rm->getTransaction( RecordManager::TRANSACTION_LAST_USED ), 0 );
        $this->assertSame( $rm->getTransaction( RecordManager::TRANSACTION_LAST_CREATED ), 0 );

        $this->assertNull( $rm->getTransaction( 'clearly not a transaction', false ) );

        $this->setExpectedException('Bond\\RecordManager\\Exception\\TransactionDoesNotExistException');
        $rm->getTransaction( 'clearly not a transaction' );

    }

    public function testGetTransaction2()
    {

        $rm = $this->entityManager->recordManager;
        $rm->newTransaction('spanner');

        $this->assertSame( $rm->getTransaction( RecordManager::TRANSACTIONS_ALL ), array('spanner') );
        $this->assertSame( $rm->getTransaction( RecordManager::TRANSACTION_LAST_USED ), 'spanner' );
        $this->assertSame( $rm->getTransaction( RecordManager::TRANSACTION_LAST_CREATED ), 'spanner' );

        $this->assertSame( $rm->getQueue( RecordManager::TRANSACTIONS_ALL ), array( 'spanner' => array() ) );
        $this->assertSame( $rm->getQueue( RecordManager::TRANSACTION_LAST_USED ), array() );
        $this->assertSame( $rm->getQueue( RecordManager::TRANSACTION_LAST_CREATED ), array() );

        $this->assertSame( $rm->getQueue( RecordManager::TRANSACTION_LAST_USED, true, true ), array('spanner' => array() ) );
        $this->assertSame( $rm->getQueue( RecordManager::TRANSACTION_LAST_CREATED, true, true ), array( 'spanner' => array() ) );

        $rm->newTransaction('monkey',true);
        $this->assertSame( $rm->getTransaction( RecordManager::TRANSACTION_LAST_USED ), 'monkey' );

        $this->assertNull( $rm->getQueue( 'clearly not a transaction', false ) );

        $this->setExpectedException('Bond\\RecordManager\\Exception\\TransactionDoesNotExistException');
        $rm->getQueue( 'clearly not a transaction' );

    }

    public function testRemoveTransaction1()
    {
        $rm = $this->entityManager->recordManager;
        $rm->newTransaction('spanner');

        $rm->removeTransaction();
        $this->assertSame( 0, count( $rm->getTransaction( RecordManager::TRANSACTIONS_ALL ) ) );
    }

    public function testRemoveTransaction2()
    {
        $rm = $this->entityManager->recordManager;
        $rm->newTransaction('one');
        $rm->newTransaction('two');
        $rm->removeTransaction( RecordManager::TRANSACTIONS_ALL );

        $this->assertSame( 0, count( $rm->getTransaction( RecordManager::TRANSACTIONS_ALL ) ) );
    }

    public function testRemoveTransaction3()
    {
        $rm = $this->entityManager->recordManager;
        $rm->newTransaction('one');
        $rm->newTransaction('two');
        $rm->removeTransaction( RecordManager::TRANSACTION_LAST_USED );

        $transactions = $rm->getTransaction( RecordManager::TRANSACTIONS_ALL );
        $this->assertSame( 1, count( $transactions ) );
    }

    public function testRemoveTransaction4()
    {
        $rm = $this->entityManager->recordManager;
        $rm->newTransaction('one');
        $rm->newTransaction('two');
        $this->assertSame( $rm->removeTransaction( RecordManager::TRANSACTION_LAST_CREATED ), 1 );

        $transactions = $rm->getTransaction( RecordManager::TRANSACTIONS_ALL );
        $this->assertSame( 1, count( $transactions ) );
    }

    public function testRemoveTransaction5()
    {
        $rm = $this->entityManager->recordManager;
        $rm->newTransaction('one');
        $rm->newTransaction('two');
        $this->assertSame( $rm->removeTransaction( array('one', 'two') ), 2 );

        $transactions = $rm->getTransaction( RecordManager::TRANSACTIONS_ALL );
        $this->assertSame( 0, count( $transactions ) );
    }

    public function testRemoveTransaction6()
    {
        $rm = $this->entityManager->recordManager;
        $rm->newTransaction('one');
        $rm->newTransaction('two');
        $this->assertSame( $rm->removeTransaction( array('one', 'two', 'three'), false ), 2 );

        $transactions = $rm->getTransaction( RecordManager::TRANSACTIONS_ALL );
        $this->assertSame( 0, count( $transactions ) );
    }

    public function testRemoveTransaction7()
    {
        $rm = $this->entityManager->recordManager;
        $this->setExpectedException('Bond\\RecordManager\\Exception\\TransactionDoesNotExistException');
        $this->assertSame( $rm->removeTransaction( array('one', 'two') ), 0 );
    }

    public function testRemoveTransaction8()
    {
        $rm = $this->entityManager->recordManager;
        $this->setExpectedException('Bond\\RecordManager\\Exception\\TransactionDoesNotExistException');
        $this->assertSame( $rm->removeTransaction( 'not a real transaction'), 0 );
    }

    public function testPersist1()
    {

        $rm = $this->entityManager->recordManager;

        $this->setExpectedException('InvalidArgumentException');
        $rm->persist( "spanner" );

    }

    public function testPersist2()
    {

        $rm = $this->entityManager->recordManager;

        $task = new Base($rm);
        $this->assertSame( $rm->persist( $task ), $rm );
        $rm->persist( $task );
        $this->assertSame( $rm->getQueue(), array(array($task,$task)) );

    }

    public function testPersist3()
    {

        $rm = $this->entityManager->recordManager;

        $tasks = <<<SQL
DROP TABLE IF EXISTS t;
CREATE TEMPORARY TABLE t AS SELECT 1 as b;
UPDATE t SET b = 2;
SQL;

        foreach( explode("\n", $tasks ) as $task ) {
            $rm->persist( new Query( $task ) );
        }

        $response = $rm->flush();

        $this->assertTrue( $response->isSuccess() );
        $this->assertSame( $rm->getQueue(), array() );

        $this->assertSame(
            $rm->db->query( new Query("SELECT b FROM t;") )->fetch( Result::FETCH_SINGLE ),
            "2"
        );

    }

    public function testPersist5()
    {

        $rm = $this->entityManager->recordManager;

        $tasks = <<<SQL
DROP TABLE IF EXISTS t;
spanner;
SQL;

        foreach( explode("\n", $tasks ) as $task ) {
            $rm->persist( new Query( $task ) );
        }
        $response = $rm->flush( RecordManager::TRANSACTIONS_ALL, RecordManager::FLUSH_ABORT, false );

        $this->assertFalse( $response->isSuccess() );
        $this->assertTrue( $response->isRolledback() );

    }

    public function testFlushThrowsExceptions1()
    {

        $rm = $this->entityManager->recordManager;
        $rm->persist( new Query("not valid sql") );
        $response = $rm->flush( RecordManager::TRANSACTIONS_ALL, RecordManager::FLUSH_ABORT, false );

        $this->assertFalse( $response->isSuccess() );
        $this->assertTrue( $response->isRolledback() );

    }

    public function testFlushThrowsExceptions2()
    {

        $rm = $this->entityManager->recordManager;
        $rm->persist( new Query("not valid sql") );

        $this->setExpectedException("Bond\Pg\Exception\QueryException");
        $response = $rm->flush( RecordManager::TRANSACTIONS_ALL, RecordManager::FLUSH_ABORT, true );

    }

    public function testFlushDontThrowExceptions()
    {

        $rm = $this->entityManager->recordManager;
        $rm->persist( new Query("not valid sql") );
        $response = $rm->flush( RecordManager::TRANSACTIONS_ALL, RecordManager::FLUSH_ABORT, false );

    }

    public function testPersistContinue()
    {

        $rm = $this->entityManager->recordManager;

        $tasks = <<<SQL
DROP TABLE IF EXISTS t;
CREATE TEMPORARY TABLE t AS SELECT 1 as b;
UPDATE t SET b = 2;
spanner;
UPDATE t SET b = 4;
SQL;

        foreach( explode("\n", $tasks ) as $num => $task ) {
            $rm->newTransaction( "transaction-{$num}" );
            $rm->persist( new Query( $task ) );
        }

        $response = $rm->flush( RecordManager::TRANSACTIONS_ALL, RecordManager::FLUSH_CONTINUE, false );

        $this->assertFalse( $response->isSuccess() );
        $this->assertTrue( $response->isRolledback() );
        $this->assertTrue( $response->doneAnything() );
        $this->assertSame( $rm->getQueue(), array() );

        $this->assertSame(
            $rm->db->query( new Query("SELECT b FROM t;") )->fetch( Result::FETCH_SINGLE ),
            "4"
        );

    }

    public function testPersist2Transactions()
    {

        $rm = $this->entityManager->recordManager;

        $tasks = <<<SQL
DROP TABLE IF EXISTS t;
CREATE TEMPORARY TABLE t AS SELECT 1 as b;
UPDATE t SET b = 2;
spanner;
UPDATE t SET b = 4;
SQL;

        foreach( explode("\n", $tasks ) as $num => $task ) {
            $rm->newTransaction( "t-{$num}" );
            $rm->persist( new Query( $task ) );
        }

        $response = $rm->flush(
            array( 't-0', 't-1' ),
            RecordManager::FLUSH_ABORT,
            false
        );

        $this->assertTrue( $response->isSuccess() );
        $this->assertFalse( $response->isRolledback() );
        $this->assertSame(
            $rm->db->query( new Query("SELECT b FROM t;") )->fetch( Result::FETCH_SINGLE ),
             "1"
        );

        $queue = $rm->getQueue();
        $this->assertSame( array_keys( $queue ), array( 't-2', 't-3', 't-4' ) );

    }

    public function testPersistTransactionLone()
    {

        $rm = $this->getRecordManagerWith3Transactions();

        $this->assertSame( count( $rm->getQueue() ), 3 );

        $response = $rm->flush('one');

        $this->assertSame( count( $rm->getQueue() ), 2 );
        $this->assertSame( count( $response->queue ), 1 );

        $this->assertSame( $rm->db->query( new Query("SELECT b FROM t;") )->fetch( Result::FETCH_SINGLE ), "2" );

        $response = $rm->flush('three');
        $this->assertSame( $rm->db->query( new Query("SELECT b FROM t;") )->fetch( Result::FETCH_SINGLE ), "6" );

    }

    public function testPersistTransactionsAllContinue()
    {

        $rm = $this->getRecordManagerWith3Transactions();

        $response = $rm->flush( RecordManager::TRANSACTIONS_ALL, RecordManager::FLUSH_CONTINUE, false );

        $this->assertSame( count( $rm->getQueue() ), 0 );
        $this->assertSame( count( $response->queue ), 3 );

        $this->assertSame( $rm->db->query( new Query( "SELECT b FROM t"))->fetch( Result::FETCH_SINGLE ), "6" );

    }

    public function testPersistTransactionsAllAbort()
    {

        $rm = $this->getRecordManagerWith3Transactions();

        $response = $rm->flush( RecordManager::TRANSACTIONS_ALL, RecordManager::FLUSH_ABORT, false );

        $this->assertSame( count( $rm->getQueue() ), 0 );
        $this->assertSame( count( $response->queue ), 3 );

        $this->assertSame( $rm->db->query( new Query( "SELECT b FROM t"))->fetch( Result::FETCH_SINGLE ), "2" );

    }

    public function getRecordManagerWith3Transactions()
    {

        $rm = $this->entityManager->recordManager;

        $tasks = <<<SQL
DROP TABLE IF EXISTS t;
CREATE TEMPORARY TABLE t AS SELECT 1 as b;
UPDATE t SET b = 2;
SQL;

        $rm->newTransaction('one');
        foreach( explode("\n", $tasks ) as $task ) {
            $rm->persist( new Query( $task ), 'one' );
        }

        $tasks = <<<SQL
DROP TABLE IF EXISTS t;
spanner;
SQL;

        $rm->newTransaction('two');
        foreach( explode("\n", $tasks ) as $task ) {
            $rm->persist( new Query( $task ), 'two' );
        }

        $tasks = <<<SQL
UPDATE t SET b = 6;
SQL;

        $rm->newTransaction('three');
        foreach( explode("\n", $tasks ) as $task ) {
            $rm->persist( new Query( $task ), 'three' );
        }

        return $rm;

    }

}