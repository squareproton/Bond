<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

use Bond\Exception\DepreciatedException;
use Bond\Exception\BadPropertyException;
use Bond\Serialize;
use Bond\Sql\SqlInterface;

abstract class SqlQueryBase implements SqlInterface, \JsonSerializable
{

    use Serialize;

    /**
     * @var string we're going to work on
     */
    protected $sql;

    /**
     * Data that will be substituted into the query
     * @var array
     */
    protected $data;

    /**
     * Standard constructor
     * @param string $sql
     * @param array $
     */
    public function __construct( $sql = null, array $data = array() )
    {
        $this->sqlSet( $sql );
        $this->data = $data;
    }

    /**
     * Get the contents of the data array
     * @return array
     */
    public function dataGet()
    {
        return $this->data;
    }

    /**
     * Set the contents of the data array
     * @param array $data
     * @return QueryBase
     */
    public function dataSet( array $data )
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Standard getter, manages $this->data
     * @param string property name
     * @return mixed
     */
    public function __get( $key )
    {
        if( array_key_exists( $key, $this->data ) ) {
            return $this->data[$key];
        }
        throw new BadPropertyException( $key, $this );
    }

    /**
     * Standard setter, manages $this->data
     * @param string property name
     * @param mixed $value
     */
    public function __set( $key, $value )
    {
        if( !is_scalar($key) ) {
            throw new BadTypeException($key, "scalar");
        }
        $this->data[$key] = $value;
    }

    /**
     * Standard issetter, manages $this->data
     * @param string property name
     * @return bool
     */
    public function __isset( $key )
    {
        return isset($this->data[$key]);
    }

    /**
     * Standard unsetter, manages $this->data
     * @param string property name
     */
    public function __unset( $key )
    {
        unset( $this->data[$key] );
    }

    /**
     * @inheritDoc
     */
    public function toArray()
    {
        return $output = [ $this->sql, $this->data ];
    }

    /**
     * @inheritDoc
     */
    public function fromArray( array $input )
    {
        $this->sqlSet( $input[0] );
        $this->data = $input[1];
    }

    /**
     * Return $this->sql
     * @return string;
     */
    public function sqlGet()
    {
        return $this->sql;
    }

    /**
     * Set the value of $this->sql
     * @return string;
     */
    public function sqlSet( $sql )
    {
        $this->sql = (string) $sql;
        return $this;
    }

    /**
     * Json serialize, blah blah
     */
    public function jsonSerialize()
    {
        return array(
            'sql' => $this->sql,
            'data' => $this->data,
        );
    }

}