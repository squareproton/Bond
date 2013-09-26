<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Debug;
use Bond\Pg;
use Bond\Sql\Query;
use Bond\Sql\Raw;

use Bond\Profiler;

use Bond\RecordManager\EventEmitter;
use Bond\RecordManager\EventEmitter\Tick;

use Bond\RecordManager\Exception\BadOperationException;
use Bond\RecordManager\Exception\BadTaskException;
use Bond\RecordManager\Exception\TransactionDoesNotExistException;

use Bond\RecordManager\Response;
use Bond\RecordManager\Task;

/**
 * Record manager
 */
class RecordManager
{

    /**
     * Class constants
     */
    const TRANSACTION_LAST_USED = '__TRANSACTION_LAST_USED';
    const TRANSACTION_LAST_CREATED = '__TRANSACTION_LAST_CREATED';
    const TRANSACTION_NEW = '__TRANSACTION_NEW';
    const TRANSACTIONS_ALL = '__TRANSACTIONS_ALL';

    const FLUSH_CONTINUE = '__FLUSH_CONTINUE';
    const FLUSH_ABORT = '__FLUSH_ABORT';

    const DEBUG_SIMULATE = 1;
    const DEBUG_SHOW_QUERY = 2;

    /**
     * Record of the last used transaction
     * @var scalar
     */
    private $lastTransaction = null;

    /**
     * Queue.
     * @var array of transactions.
     */
    private $queue = [];

    /**
     * Debug.
     */
    private $debug = 0;

    /**
     * EntityManager
     * Bond\EntityManager
     */
    private $entityManager;

    /**
     * Database connection
     * Bond\Pg
     */
    private $db;

    /**
     * Is the transaction tick supported
     */
    private $eventEmitter;

    /**
     * RegisteredTaskHanders
     */
    private $taskHandlers = [];

    /**
     * Protected constructor for singleton
     */
    public function __construct( EntityManager $entityManager, EventEmitter $eventEmitter = null )
    {

        $this->entityManager = $entityManager;
        $this->db = $this->entityManager->db;

        if( !$eventEmitter ) {
            $eventEmitter = new Tick();
        }
        $this->eventEmitter = $eventEmitter;
        // $this->newTransaction(null, true);

        // Right now this is shit and if definately needs DI-ifying
        $this->registerTaskHandler( \Bond\RecordManager\Task\NormalityCollection::class );
        $this->registerTaskHandler( \Bond\RecordManager\Task\Normality::class );
        $this->registerTaskHandler( \Bond\RecordManager\Task\PgLargeObject::class );
        $this->registerTaskHandler( \Bond\RecordManager\Task\Query::class );

    }

    /**
     * Register task handler
     */
    public function registerTaskHandler( $class )
    {
        $this->taskHandlers[] = $class;
    }

    /**
     * Decide if how we want to save this
     * $object
     */
    public function getTask( $object, $operation, $exceptionOnFailure = true )
    {

        /* debugging
        printf(
            "\n-- new task %s%s %s\n",
            get_class( $object ),
            $object instanceof \Bond\Entity\Container ? get_unqualified_class( $object->classGet() ) : null,
            count( $object )
        );
         */

        if( !in_array( $operation, array( Task::PERSIST, Task::DELETE ), true ) ) {
            throw new BadOperationException( "Unknown task operation `{$operation}`; you probably want `RecordManager::PERSIST` or `RecordManager::DELETE`" );
        }

        // tasks only work on objects
        if( !is_object($object) ) {
            throw new BadTaskException("Tasks can only operate on objects");
        }

        // detect object
        foreach( $this->taskHandlers as $type ){

            if( call_user_func( [$type, 'isCompatible'], $object ) ) {
                $taskType = $type;
                break;
            }

        }

        // Can't handle this? What now?
        if( !isset( $taskType ) ) {

            // exceptionOnFailure
            if( $exceptionOnFailure ) {
                $class = get_class( $object );
                throw new BadTaskException( "Unable to find a Task compatible which works for object `{$class}` and operation `{$operation}`" );
            }

            // No exception
            return null;

        }

        // Normality variants?
        if(
            strpos( $taskType, 'Normality' ) !== false or
            strpos( $taskType, 'PgLargeObject' ) !== false
        ) {
            $taskType .= ( $operation === Task::DELETE ) ? '\\Delete' : '\\Persist';
        }

        // Instantiate
        $reflClass = new \ReflectionClass($taskType);
        $task = $reflClass->newInstance($this);
        // Set object property. No need to go via the setter because we have already determined this is compatible
        $reflProperty = $reflClass->getProperty('object');
        $reflProperty->setAccessible(true);
        $reflProperty->setValue( $task, $object );

        return $task;

    }

    /**
     * Set the debug state
     * @param booly $state The requested state
     * @return bool The state just set.
     */
    public function debug( $state = null )
    {
        $default = self::DEBUG_SIMULATE + self::DEBUG_SHOW_QUERY;
        if( is_bool( $state ) ) {
            $state = $state ? $default : 0;;
        } elseif ( $state === null ) {
            $state = $default;
        }
        $this->debug = $state;
        return $this->debug;
    }

    /**
     * Create a new transaction
     *
     * @param scalar Optional. Transaction name
     * @param booly Set update $this->lastTransaction pointer to refer to this new transaction
     *
     * @return scalar New transaction name
     */
    public function newTransaction( $name = null, $updateLastTransaction = true )
    {

        // no name
        if( !isset( $name ) ) {

            $this->queue[] = array();
            $name = $this->getTransaction( self::TRANSACTION_LAST_CREATED );

        // name already taken
        } elseif( array_key_exists( $name, $this->queue ) ) {

            throw new \InvalidArgumentException("Transaction with name `{$name}` already exists can't create new named transaction.");

        // new transaction
        } elseif( !\is_scalar($name) ) {

            throw new \InvalidArgumentException("Transaction name needs to be a scalar." );

        } else {

            $this->queue[$name] = array();

        }

        if( $updateLastTransaction ) {
            $this->lastTransaction = $name;
        }

        return $name;

    }

    /**
     * Add a item to the queue for persitance on flush
     *
     * @param object $object ... to persist
     * @param scalar $namedTransaction Transaction to persist this entity within
     *
     * @return true
     */
    public function persist( $object, $transaction = self::TRANSACTION_LAST_USED )
    {
        if( !is_object( $object ) ) {
            throw new \InvalidArgumentException("I can only persist objects.");
        }

        if( !( $object instanceof Task) ) {
            $object = $this->getTask( $object, Task::PERSIST );
        }

        $transaction = $this->getTransaction($transaction, true);
        $this->queue[$transaction][] = $object;

        return $this;
    }

    /**
     * Add a item to the queue for persitance on flush
     *
     * @param object $object ... to delete
     * @param scalar $namedTransaction Transaction to persist this entity within
     *
     * @return true
     */
    public function delete( $object, $transaction = self::TRANSACTION_LAST_USED )
    {
        // this'll throw a exception if anything isn't right
        $task = $task->getTask( $object, Task::DELETE );
        $transaction = $this->getTransaction($transaction,true);
        $this->queue[$transaction][] = $task;
        return $this;
    }

    /**
     * Add a item to the queue for persitance on flush
     *
     * @param $trancation
     * @param $onFailure
     *
     * @return bool
     */
    public function flush( $transaction = null, $onFailure = self::FLUSH_ABORT, $throwExceptions = true )
    {

        if( is_null( $transaction ) ) {
            $transaction = self::TRANSACTIONS_ALL;
        }

        if( is_null( $onFailure) ) {
            $onFailure = self::FLUSH_ABORT;
        }

        $response = new Response();
        $exceptions = [];
        $haveAnyTransactionsFailed = false;

        // simulate
        $simulate = ( $this->debug & self::DEBUG_SIMULATE );

        // profiler
        $profiler = new Profiler( "Record manager" );
        $profiler->log();

        // debugging query show
        $debugQuery = null;
        if( $this->debug & self::DEBUG_SHOW_QUERY ) {
            $debugQuery = function ( $e, $event, $data ) {
                echo $data . "\n";
            };
            $this->db->debug->on( Debug::INFO, $debugQuery );
        }

        // trap all throw exceptions so that we can properly unattach the $db->debug->on( Pg::QueryPassed $listener )
        try {

            // iterate selected transactions
            foreach( $this->getQueue( $transaction, true, true ) as $transaction => $queue ) {

                unset( $this->queue[$transaction] );

                $taskResponse = null;
                $hasTransactionFailed = false;

                $beginTransaction = new Query( "BEGIN TRANSACTION READ WRITE; /* %transaction:% */" );
                $beginTransaction->transaction = $transaction;
                $this->db->query( $beginTransaction );

                $this->eventEmitter->emit( EventEmitter::TRANSACTION_START, $this, $transaction, $queue );

                // loop over our tasks
                foreach( $queue as $task ) {

                    // are we continuing to process?
                    if( $hasTransactionFailed or ( $haveAnyTransactionsFailed && $onFailure === self::FLUSH_ABORT ) ) {
                        $taskResponse = Response::ABORTED;
                    } else {
                        try {
                            $taskResponse = $task->execute( $this->db, $simulate ) ? Response::SUCCESS : Response::FAILED;
                        } catch( \Exception $e ) {
                            // It'd be nice if we could just test for Query exceptions here but unfortunately PHPUnit dicks pretty hard with exception types.
                            // @Mr Beale. I realise this violates the "only handle exceptions that you understand" rule but I can't find a way round this. Pete
                            $taskResponse = $e;
                            $exceptions[] = $e;
                        }
                    }

                    // transaction success
                    if( $taskResponse !== Response::SUCCESS ) {
                        $hasTransactionFailed = true;
                        $haveAnyTransactionsFailed = true;
                    }

                    $response->add( $transaction, $task, $taskResponse );

                }

                // end or rollback transaction
                $endTransaction = new Query( "%commitOrRollback:% TRANSACTION; /* %transaction:% */" );
                $endTransaction->commitOrRollback = new Raw($hasTransactionFailed ? 'ROLLBACK' : 'COMMIT');
                $endTransaction->transaction = $transaction;
                $this->db->query( $endTransaction );

                // response rollback
                if( $hasTransactionFailed ) {
                    $response->rollback( $transaction );
                }

                $profiler->log( $transaction );

            }

            // if we've gathered any exceptions throw them here
            if( $throwExceptions and $exceptions ) {
                throw $exceptions[0];
            }

            $response->profilerAssign( $profiler );

        } catch ( \Exception $e ) {
            $this->db->debug->removeListener( Pg::QUERY_PARSED, $debugQuery );
            throw $e;
        }

        return $response;

    }

    /**
     * Get a transaction name
     *
     * @param scalar The name of a transaction;
     * @param bool Throw exception if we can't find.
     *
     * @return scalar|false in the event of not found
     */
    public function getTransaction( $transaction = self::TRANSACTION_LAST_USED, $throwExceptionIfNotFound = true )
    {

        if( $transaction === self::TRANSACTIONS_ALL ) {
            return array_keys( $this->queue );
        }

        if( $transaction === self::TRANSACTION_LAST_CREATED ) {
            if( !$this->queue) {
                $this->newTransaction();
            }
            $queue = array_keys( $this->queue );
            return array_pop( $queue );
        }

        if( $transaction === self::TRANSACTION_LAST_USED ) {
            if( !$this->queue) {
                $this->newTransaction();
            }
            return $this->lastTransaction;
        }

        if( $transaction === self::TRANSACTION_NEW ) {
            return $this->newTransaction();
        }

        if( !array_key_exists( $transaction, $this->queue ) ) {
            if( $throwExceptionIfNotFound ) {
                throw new TransactionDoesNotExistException(
                    "Transaction with this name `{$transaction}` doesn't exist. Sorry."
                );
            }
            return null;
        }

        return $transaction;

    }

    /**
     * Remove a transaction
     *
     * @param scalar|array The name of the transaction
     * @param bool Throw a exception if the transaction we want to remove can't be found
     *
     * @return int The number of transactions removed
     */
    public function removeTransaction( $transaction = self::TRANSACTION_LAST_USED, $throwExceptionIfNotFound = true )
    {

        // remove all transactions
        if( $transaction === self::TRANSACTIONS_ALL ) {
            $numTransactions = count( $this->queue );
            $this->queue = array();
            $this->lastTransaction = null;
            return $numTransactions;
        }

        // last created transaction
        if( $transaction === self::TRANSACTION_LAST_CREATED ) {
            $keys = array_keys( $this->queue );
            if( $transaction = array_pop( $keys ) ) {
                if( $this->lastTransaction === $transaction ) {
                    $this->lastTransaction = null;
                }
                unset( $this->queue[$transaction] );
                return 1;
            }
            return 0;
        }

        // last used
        if( $transaction === self::TRANSACTION_LAST_USED ) {
            if( $this->lastTransaction ) {
                unset( $this->queue[$this->lastTransaction] );
                $this->lastTransaction = null;
                return 0;
            }
            return 0;
        }

        // array of transactions
        if( is_array( $transaction ) ) {
            $output = 0;
            foreach( $transaction as $name ) {
                if( array_key_exists( $name, $this->queue ) ) {
                    if( $this->lastTransaction === $name ) {
                        $this->lastTransaction = null;
                    }
                    unset( $this->queue[$name] );
                    $output++;
                } elseif ( $throwExceptionIfNotFound ) {
                    throw new TransactionDoesNotExistException(
                        "Transaction with this name `{$name}` doesn't exist. Sorry."
                    );
                }
            }
            return $output;
        }

        if( is_scalar($transaction) ) {
            if( array_key_exists( $transaction, $this->queue ) ) {
                if( $this->lastTransaction === $transaction ) {
                    $this->lastTransaction = null;
                }
                unset( $this->queue[$name] );
                return 1;
            } elseif ( $throwExceptionIfNotFound ) {
                throw new TransactionDoesNotExistException(
                    "Transaction with this name `{$transaction}` doesn't exist. Sorry."
                );
            }
            return 0;
        }

        throw new \InvalidArgumentException(
            sprintf(
                "Not sure how to remove transaction `%s`",
                print_r( $transaction, true )
            )
        );

    }

    /**
     * Get queue.
     * @param array|scalar The name of a transaction or a array or transactions
     * @param bool Throw exception if we can't find
     *
     * @return array The queue
     */
    public function getQueue( $transaction = self::TRANSACTIONS_ALL, $throwExceptionIfNotFound = true, $outputAsArray = false )
    {

        if( $transaction === self::TRANSACTIONS_ALL ) {

            return $this->queue;

        // passed a array of transactions
        } elseif( is_array( $transaction ) ) {
            if( $throwExceptionIfNotFound and $missing = array_diff( $transaction, array_keys( $this->queue ) ) ) {
                $missing = implode( ', ', $missing );
                throw new TransactionDoesNotExistException(
                    "The following requested named transactions do not exist `{$missing}`"
                );
            }
            // this is a array_intersect *with ordering*
            $output = [];
            foreach( $transaction as $name ) {
                $output[$name] = $this->queue[$name];

            }
            return $output;
        }

        $transaction = $this->getTransaction( $transaction, $throwExceptionIfNotFound );
        if( $transaction !== null ) {
            return $outputAsArray
                ? array( $transaction => $this->queue[$transaction] )
                : $this->queue[$transaction];
        }

        return $outputAsArray ? array() : null;

    }

    /**
     * Standard getter
     */
    public function __get( $key )
    {
        switch( $key ) {
            case 'db':
            case 'debug':
            case 'entityManager':
                return $this->$key;
        }
        throw new \Bond\Exception\UnknownPropertyForMagicGetterException($this, $key);
    }

}