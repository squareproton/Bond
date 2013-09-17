<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager;

use Bond\Pg;

use Bond\RecordManager\Exception\BadTaskException;
use Bond\Exception\DepreciatedException;
use Bond\RecordManager;

/**
 * Operation
 */
abstract class Task
{

    const INSERT = '__QUERY_INSERT';
    const UPDATE = '__QUERY_UPDATE';
    const DELETE = '__QUERY_DELETE';
    const SELECT = '__QUERY_SELECT';
    const PERSIST = '__PERSIST';

    /**
     * Object we're going to save
     * @var mixed
     */
    protected $object;

    /**
     * A reference to the record manager
     * @param Bond\RecordManager
     */
    protected $recordManager;

    /**
     * RecordManager
     */
    public function __construct( RecordManager $rm )
    {
        $this->recordManager = $rm;
    }

    /**
     *
     */
    public static function init()
    {
        throw new DepreciatedException("Intantiate tasks via the RecordManager");
    }

    /**
     * Get a task's object
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Set a task's object property
     *
     * @param object $object
     * @param booly Throw exception if the even we can't set the object
     *
     * @return Were we able to set this property
     */
    public function setObject( $object, $throwExceptionOnFailure = true )
    {

        // tasks only work on objects
        if( !is_object($object) ) {
            throw new BadTaskException("A task must be object");
            return false;
        }

        $error = null;

        if( $isCompatible = static::isCompatible( $object, $error ) ) {
            $this->object = $object;
            return true;
        }

        if( $throwExceptionOnFailure ) {
            throw new BadTaskException("{$error}");
        }
        return false;

    }

    /**
     * Execute this task
     *
     * @param Pg $pg connection
     * @param bool Simulate
     * @return Query success
     */
    public function execute( Pg $pg, $simulate = false )
    {
        throw new \LogicException("You need to overload ->execute()");
    }

    /**
     * Is the passed object compatible with RecordManager\Task ?
     * @param object $object
     * @param string $error
     */
    public static function isCompatible( $object, &$error )
    {
        throw new \LogicException("You need to overload ->isCompatible() in all descendant entities.");
    }

}
