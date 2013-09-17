<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager;

use Bond\RecordManager;
use Bond\RecordManager\Task;

use Bond\RecordManager\Exception\TransactionDoesNotExistException;
use Bond\RecordManager\Exception\BadStatusException;

use Bond\Profiler;

class Response implements \Countable, \JsonSerializable
{

    /**
     * Class constants
     */
    const SUCCESS = '__SUCCESS';
    const FAILED = '__FAILED';
    const ABORTED = '__ABORTED';
    const ROLLEDBACK = '__ROLLEDBACK';

    /**
     * Store of types
     * @var array
     */
    protected $queue = [];

    /**
     * Store of task status'
     * @var array
     */
    protected $status = [];

    /**
     * Store of exceptions
     * @var array
     */
    protected $exceptions = [];

    /**
     * Attach profiler
     */
    private $profiler;

    /**
     * @param scalar $transaction Name of trsnsaction as understood by RecordManager
     * @param Task $task
     * @param string $status One of the class constants
     */
    public function add( $transaction, Task $task, $status )
    {

        if( $status instanceof \Exception ) {
            $exception = $status;
            $status = self::FAILED;
        } else {
            $exception = null;
        }

        if( !in_array( $status, array(self::SUCCESS, self::FAILED, self::ABORTED, self::ROLLEDBACK) ) ) {
            throw new BadStatusException("Task status `{$status}` isn't valid.");
        }

        $this->queue[$transaction][] = $task;
        $this->status[$transaction][] = $status;
        $this->exceptions[$transaction][] = $exception;

    }

    /**
     * Rollback a named transaction
     * @param <type> $transaction
     */
    public function rollback( $transaction )
    {
        $status = $this->getStatus( $transaction);
        if( count($status) !== 1 ) {
            throw new \LogicException("You can't rollback more than 1 transaction");
        }

        list( $transaction, $transactionStatus ) = each( $status );

        // rollback things which were attempted
        foreach( $transactionStatus as $key => $taskStatus ) {
            if( $taskStatus === self::SUCCESS ) {
                $this->status[$transaction][$key] = self::ROLLEDBACK;
            }
        }

    }

    /**
     * Has a named transaction been executed successfully
     * @param scalar $transaction
     * @return bool
     */
    public function isSuccess( $transaction = RecordManager::TRANSACTIONS_ALL )
    {
        $output = true;
        foreach( $this->getStatus($transaction) as $transaction => $transactionStatus ) {
            foreach( $transactionStatus as $status ) {
                $output = ( $output && $status === self::SUCCESS );
            }
        }
        return $output;
    }

    /**
     * Has a named transaction been rolledback
     * @param scalar $transaction
     * @return bool
     */
    public function isRolledback( $transaction = RecordManager::TRANSACTIONS_ALL )
    {
        $output = false;
        foreach( $this->getStatus($transaction) as $transaction => $transactionStatus ) {
            foreach( $transactionStatus as $status ) {
                $output = ( $output || $status === self::ROLLEDBACK || $status === self::FAILED );
            }
        }
        return $output;
    }

    /**
     * Have we actually done anything?
     * @return bool
     */
    public function doneAnything()
    {
        $output = false;
        foreach( $this->status as $transaction => $transactionStatus ) {
            foreach( $transactionStatus as $status ) {
                $output = ( $output || $status === self::SUCCESS );
            }
        }
        return $output;
    }

    /**
     * Standard __get(ter)
     * @param scalar $key
     * @return mixed
     */
    public function __get( $key )
    {
        switch( $key ) {
            case 'queue':
            case 'status':
            case 'exceptions':
            case 'profiler':
                return $this->$key;
                break;
            case 'exceptionsFlat':
                $exceptionsFlat = array();
                foreach( $this->exceptions as $transactionExceptions ) {
                    foreach( $transactionExceptions as $exception ) {
                        if( $exception ) {
                            $exceptionsFlat[] = $exception;
                        }
                    }
                }
                return $exceptionsFlat;
                break;
        }
        throw new \InvalidArgumentException("property `{$key}` unknown to __get()");
    }

    /**
     * Get status'
     * @param scalar The name of a transaction
     * @return array The status
     */
    public function getStatus( $transaction = RecordManager::TRANSACTIONS_ALL )
    {

        if( $transaction === RecordManager::TRANSACTIONS_ALL ) {
            return $this->status;
        }

        if( !array_key_exists( $transaction, $this->status ) ) {
            throw new TransactionDoesNotExistException("Transaction with this name `{$transaction}` doesn't exist. Sorry.");
        }

        return array( $transaction => $this->status[$transaction] );

    }

    /**
     * getExceptionMessages()
     * @return array
     */
    public function getExceptionMessages()
    {
        $messages = array();
        foreach( $this->__get('exceptionsFlat') as $exception ) {
            $messages[] = $exception->getMessage();
        }
        return $messages;
    }

    /**
     * Get transaction response by object
     * @param object|array $object
     * @return $response
     */
    public function filterByObject( $objectOrArray )
    {

        $response = new Response();

        if( !is_array($objectOrArray) ) {
            $objectOrArray = array( $objectOrArray );
        }

        foreach( $objectOrArray as $object ) {

            if( !is_object($object) ) {
                throw new \InvalidArgumentException("can't findTransactionByObject if not object" );
            }

            // iterate over t
            foreach( $this->queue as $transaction => $transactionQueue ) {
                foreach( $transactionQueue as $key => $task ) {
                    if( $object === $task or $object === $task->getObject() ) {
                        $response->add(
                            $transaction,
                            $task,
                            isset( $this->exceptions[$transaction][$key] )
                                ? $this->exceptions[$transaction][$key]
                                : $this->status[$transaction][$key]
                        );
                    }
                }
            }

        }

        return $response;

    }

    /**
     * Attach a profiler
     */
    public function profilerAssign( Profiler $profiler )
    {
        $this->profiler = $profiler;
    }

    /**
     * Returns the number of transactions executed
     * @return int
     */
    public function count()
    {
        return count( $this->status );
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return $this->isSuccess();
    }

    /**
     * toJson
     * Return a json representation this record managers response.
     * There'll be a corresponding js function which can pass
     * At the moment this does very little.
     * To be expanded ....
     */
    public function toJSON()
    {
        return json_encode( $this->isSuccess() );
    }

}
