<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg;

use Bond\Pg;
use Bond\Pg\Resource;

use Bond\Database\Exception\UnknownNamedConnectionException;
use Bond\Exception\BadTypeException;

/**
 * Database connection management class
 */
class ConnectionFactory
{

    /**
     * Multiton store
     * @var array
     */
    private $instances = [];

    /**
     * Array of server settings objects
     * @var Bond\Pg\ConnectionSettings
     */
    private $connectionSettings = [];

    /**
     * Standard setter
     * @param Bond\Pg\ConnectionSettings[]
     */
    public function __construct( $connectionSettings = [] )
    {
        foreach( $connectionSettings as $key => $settings ) {
            if( !($settings instanceof ConnectionSettings ) ) {
                throw new BadTypeException( $settings, ConnectionSettings::class );
            }
            $this->connectionSettings[$key] = $settings;
        }
    }

    /**
     * Get the singleton pg connection
     * @param string $connection
     * @param boolean $cache
     * @return \Bond\Pg
     */
    public function get( $connection, $cache = true )
    {

        if( !is_string( $connection ) ) {
            throw new BadTypeException( $connection, 'string' );
        }

        // use the multiton
        if( !isset( $this->instances[$connection] ) or !$cache ) {

            if( !isset( $this->connectionSettings[$connection]) ) {
                throw new UnknownNamedConnectionException( $connection );
            }

            $resource = new Resource( $this->connectionSettings[$connection], $this );

            if( $cache ) {
                $this->instances[$connection] = $resource;
            }

        // get the resource from the multiton
        } else {
            $resource = $this->instances[$connection];
        }

        return new Pg( $resource, $connection );
    }

    /**
     * Remove a resource from the multiton cache
     * @param Bond\Pg\Resource
     * @return int
     */
    public function remove( Resource $resource )
    {
        $cnt = 0;
        if( false !== $key = array_search( $resource, $this->instances, true ) ) {
            unset( $this->instances[$key] );
            $cnt++;
        }
        return $cnt;
    }

    /**
     * Have we cached a connection?
     * @return bool
     */
    public function has( $connection )
    {
        return isset( $this->instances[$connection] );
    }

}