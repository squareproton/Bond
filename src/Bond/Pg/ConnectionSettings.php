<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg;

use Bond\Exception\UnknownOptionException;

class ConnectionSettings implements \JsonSerializable
{

    private $connectionInfo = array(
        'host' => 'localhost',
        'port' => 5432,
        'dbname' => null,
        'user' => null,
        'password' => null,
        'search_path' => null,
    );

    public function __construct( array $connectionInfo )
    {
        foreach( $this->connectionInfo as $key => $_value ) {
            if( isset( $connectionInfo[$key] ) ) {
                $this->connectionInfo[$key] = $connectionInfo[$key];
            }
        }
    }

    /**
     * Generate a property formatted postgres connection string
     *
     * @param array $settings array( 'host' =>, 'port' =>, 'dbname' =>, 'user' => , 'password' => )
     * @return string a pg_connect compatable connectio string
     */
    public function getConnectionString()
    {

        $settings = $this->connectionInfo;
        unset( $settings['search_path'] );

        foreach ($settings as $key => &$value) {
            $value = sprintf(
                "%s='%s'",
                $key,
                $value
            );
        }

        return implode(' ', $settings);

    }

    /**
     * @param Cast connect settings to string
     * @return string
     */
    public function __toString()
    {
        return $this->getConnectionString();
    }

    public function __get( $key )
    {
        if( !array_key_exists( $key, $this->connectionInfo ) ) {
            throw new UnknownOptionException(
                $key,
                array_keys( $this->connectionInfo )
            );
        }
        return $this->connectionInfo[$key];
    }

    public function __set( $key, $value )
    {
        if( !array_key_exists( $key, $this->connectionInfo ) ) {
            throw new UnknownOptionException(
                $key,
                array_keys( $this->connectionInfo )
            );
        }
        $this->connectionInfo[$key] = $value;
    }

    public function __unset( $key )
    {
       $this->connectionInfo[$key] = null;
    }

    public function __isset( $key )
    {
       return isset( $this->connectionInfo[$key] );
    }

    public function get()
    {
        return array_filter(
            $this->connectionInfo,
            function ($value) {
                return !is_null($value);
            }
        );
    }

    public function jsonSerialize()
    {
        return $this->connectionInfo;
    }

}