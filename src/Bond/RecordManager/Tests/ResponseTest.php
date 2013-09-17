<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Tests;

use Bond\RecordManager;
use Bond\RecordManager\Response;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Base;

class ResponseTest extends \Bond\Tests\EntityManagerProvider
{

    public function testInit()
    {
        $response = new Response();

        $this->assertTrue( $response instanceof $response );
        $this->assertSame( $response->queue, array() );
        $this->assertSame( $response->status, array() );

        $this->assertTrue( $response->isSuccess() );
        $this->assertFalse( $response->isRolledBack() );
        $this->assertFalse( $response->doneAnything() );

    }

    public function testAddLoneTransaction()
    {
        $response = new Response();
        $base = new Base( $this->entityManager->recordManager );
        $response->add( 'spanner', $base, Response::SUCCESS );

        $this->assertSame( $response->queue, array( 'spanner' => array($base) ) );
        $this->assertSame( $response->status, array( 'spanner' => array(Response::SUCCESS) ) );

        $this->assertTrue( $response->isSuccess() );
        $this->assertFalse( $response->isRolledBack() );
        $this->assertTrue( $response->doneAnything() );

    }

    public function testRollingBackASuccessfullTransaction()
    {
        $response = new Response();
        $base = new Base( $this->entityManager->recordManager );
        $response->add( 'spanner', $base, Response::SUCCESS );
        $response->rollback('spanner');

        $this->assertSame( $response->queue, array( 'spanner' => array($base) ) );
        $this->assertSame( $response->status, array( 'spanner' => array(Response::ROLLEDBACK) ) );

        $this->assertFalse( $response->isSuccess() );
        $this->assertTrue( $response->isRolledBack() );
        $this->assertFalse( $response->doneAnything() );
    }

    public function testAddMultiTransactions()
    {
        $response = new Response();
        $base = new Base( $this->entityManager->recordManager );
        $response->add( 'spanner', $base, Response::SUCCESS );
        $response->add( 'goat', $base, Response::FAILED );

        $this->assertSame( $response->queue, array( 'spanner' => array($base), 'goat' => array($base) ) );
        $this->assertSame( $response->status, array( 'spanner' => array(Response::SUCCESS), 'goat' => array(Response::FAILED) ) );

        $this->assertFalse( $response->isSuccess() );
        $this->assertTrue( $response->isSuccess('spanner') );
        $this->assertFalse( $response->isSuccess('goat') );

        $this->assertTrue( $response->isRolledBack() );
        $this->assertFalse( $response->isRolledBack('spanner') );
        $this->assertTrue( $response->isRolledBack('goat') );

        $this->assertTrue( $response->doneAnything() );
    }

    public function testRollbackWithBadTransaction1()
    {
        $response = new Response();
        $response->add( 'spanner', new Base( $this->entityManager->recordManager ), Response::SUCCESS );
        $this->setExpectedException('Bond\\RecordManager\\Exception\\TransactionDoesNotExistException');
        $response->rollback('not a valid transaction');
    }

    public function testRollbackWithBadTransaction2()
    {
        $response = new Response();

        $response->add( 'spanner', new Base( $this->entityManager->recordManager ), Response::SUCCESS );
        $response->add( 'monkey', new Base( $this->entityManager->recordManager ), Response::SUCCESS );
        $this->setExpectedException('Bond\\RecordManager\\Exception\\TransactionDoesNotExistException');
        $response->rollback('not a valid transaction');
    }

    public function testIsSuccessWithBadTransaction()
    {
        $response = new Response();
        $this->setExpectedException('Bond\\RecordManager\\Exception\\TransactionDoesNotExistException');
        $response->isSuccess('not a valid transaction');
    }

    public function testGetStatusWithBadTransaction()
    {
        $response = new Response();
        $this->setExpectedException('Bond\\RecordManager\\Exception\\TransactionDoesNotExistException');
        $response->getStatus('spanner');
    }

    public function testAddWithBadArgument2()
    {
        $response = new Response();
        $this->setExpectedException('Bond\\RecordManager\\Exception\\BadStatusException');
        $response->add('spanner', new Base( $this->entityManager->recordManager ), "not a real status" );
    }

    // status with exceptions
    public function testAddExceptions()
    {
        $response = new Response();
        $message = 'some exception message';
        $exception = new \Exception($message);

        $response->add( 'spanner', new Base( $this->entityManager->recordManager ), $exception );
        $this->assertFalse( $response->doneAnything() );
        $this->assertFalse( $response->isSuccess() );

        $exceptions = $response->exceptions;
        $exceptionsFlat = $response->exceptionsFlat;

        $this->assertSame( $exceptions['spanner'][0], $exception );
        $this->assertSame( $exceptionsFlat[0], $exception );

        $messages = $response->getExceptionMessages();
        $this->assertSame( $messages[0], $message );

    }

    public function testFilterByObject()
    {

        $response = new Response();
        $obj = new \stdClass();
        $exception = new \Exception("some exception here");

        $task1 = new Base( $this->entityManager->recordManager );
        $task1->setObject( $obj );

        $task2 = new Base( $this->entityManager->recordManager );

        // build two task response
        $response->add( 'success', $task1, Response::SUCCESS );
        $response->add( 'failed', $task2, $exception );
        $this->assertSame( count($response), 2 );
        $this->assertFalse( $response->isSuccess() );

        // lookup by $obj
        $responseSuccess = $response->filterByObject( $obj );
        $this->assertTrue( $responseSuccess->isSuccess() );
        $this->assertSame( count($responseSuccess), 1 );

        // lookup by task1
        $responseSuccess = $response->filterByObject( $task1 );
        $this->assertTrue( $responseSuccess->isSuccess() );
        $this->assertSame( count($responseSuccess), 1 );

        // lookup by task2
        $responseFailed = $response->filterByObject( $task2 );
        $this->assertFalse( $responseFailed->isSuccess() );
        $this->assertSame( count($responseFailed), 1 );

        // lookup by task1,2
        $responseAll = $response->filterByObject( array( $task1, $task2 ) );
        $this->assertFalse( $responseAll->isSuccess() );
        $this->assertSame( count($responseAll), 2 );

    }

    public function testNormalityWhatWasDone()
    {

    }

}