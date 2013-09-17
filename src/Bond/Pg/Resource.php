<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg;

use Bond\Database\Exception\ConnectionTerminatedException;
use Bond\Database\Exception\UnableToConnectException;
use Bond\Database\ResourceInterface;

use Bond\Exception\BadTypeException;

use Bond\Pg\ConnectionSettings;
use Bond\Pg\Exception\MissingConnectionInformationException;

use Bond\MagicGetter;

use Serializable;

/**
 * Manager for a Postgres resource.
 */
class Resource implements Serializable, ResourceInterface
{

    use MagicGetter;

    /**
     * Slightly tweeked version of the multiton pattern.
     * This object keeps track of every instance it generates for keepalive purposes.
     * Objects are removed from here by calling->terminate()
     * @var \Bond\Pg\Resource[]
     */
    private static $instances = [];

    /**
     * ConnectionSettings
     * @var Bond\Pg\ConnectionSettings
     */
    private $connectionSettings;

    /**
     * Is terminated?
     * If a connection has been terminated it should be removed from the $instances[] and from then on become inactive.
     * @terminated
     */
    private $terminated = false;

    /**
     * If this resource was created by a connectionFactory when it is terminated it should remove itself when terminated
     * @var Bond\Pg\ConnectionFactory
     */
    private $connectionFactory;

    /**
     * @param ConnectionSettings
     */
    public function __construct( $connectionSettings, ConnectionFactory $connectionFactory = null )
    {

        if( !$connectionSettings instanceof ConnectionSettings ) {
            throw new BadTypeException( $connectionSettings, 'Bond\Pg\ConnectionSettings' );
        }

        $this->connectionSettings = $connectionSettings;

        $connectionString = $this->connectionSettings->getConnectionString();

        if( !$resource = @pg_connect( $connectionString, PGSQL_CONNECT_FORCE_NEW ) ) {
            $error = error_get_last();
            throw new UnableToConnectException( $connectionString, $error['message'], $connectionSettings->jsonSerialize() );
        }
        self::$instances[] = $this;
        $this->terminated = false;

        pg_set_error_verbosity( $resource, PGSQL_ERRORS_VERBOSE );

        // search path -- this must be sql safe
        // possible sql injection vuln here -- setting must be sql injection safe
        if( isset( $connectionSettings->search_path ) ) {
            pg_query( "SET search_path TO {$connectionSettings->search_path};" );
        }

        $this->resource = $resource;
        $this->connectionFactory = $connectionFactory;

    }

    /**
     * Ensure all known, non terminated, resource objects are golden
     */
    public static function ensure()
    {
        $cnt = 0;
        foreach( self::$instances as $instance ) {
            if( !$instance->isAlive() ) {
                $instance->reset();
                $cnt++;
            }
        }
        return $cnt;
    }

    /**
     * Return the number of database connections this script has open.
     * @return int
     */
    public static function numInstances()
    {
        return count( self::$instances );
    }

    /**
     * Serialization interface
     * @desc Serialize a database connection.
     */
    public function serialize()
    {
        return json_encode( $this->connectionSettings );
    }

    /**
     * Unserialize. Object.
     * @waring. Unserializing a resource will generate a new resource
     */
    public function unserialize($data)
    {
        $connectionSettings = new ConnectionSettings( json_decode( $data, true ) );
        $this->__construct( $connectionSettings );
    }

    /**
     * Close the database connection.
     * Wrapper for pg_close();
     */
    public function terminate()
    {

        if( $this->isTerminated() ) {
            return true;
        }

        // remove from the instances array (ie, we no longer wish to maintain or keep this connection up)
        if( false === $key = array_search( $this, self::$instances, true ) ) {
            throw new \LogicException( "Trying to terminate a connection that isn't in instances store. This is a problem - talk to Pete." );
        }
        unset( self::$instances[$key] );

        // remove from its creating connection factory if it exists there
        if( $this->connectionFactory ) {
            $this->connectionFactory->remove($this);
        }

        try {
            $output = pg_close( $this->resource );
        } catch ( \Exception $e ) {
            $output = false;
        }

        // mark the resource a terminated
        $this->terminated = array(
            'time' => time(),
//            'backtrace' => sane_debug_backtrace(),
        );

        return $output;

    }

    /**
     * Has this resource been terminated?
     * @throws Bond\Pg\ConnectionTerminated
     * @return bool
     */
    public function isTerminated( $throwExceptionIfTerminated = true )
    {
        if( $this->terminated ) {
            if( $throwExceptionIfTerminated ) {
                throw new ConnectionTerminatedException( $this );
            }
            return true;
        }
        return false;
    }

    /**
     * Get the connection status
     * @return bool
     */
    public function isAlive()
    {
        try {
            return PGSQL_CONNECTION_OK === pg_connection_status($this->resource);
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Reset the database connection
     * @return bool
     */
    public function reset()
    {
        $this->terminate();
        $this->__construct( $this->connectionSettings );
        return $this;
    }

    /**
     * Return the pglink resource.
     * @param bool Reset the connection if, for any reason, it has gone away
     * @return pg_link resource
     */
    public function get( $reconnectOnFailure = false )
    {
        if( $reconnectOnFailure and !$this->isAlive() ) {
            $this->reset();
        }
        return $this->resource;
    }

}