<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog;

use Bond\Exception\BadPropertyException;
use Bond\Pg\Catalog;

trait Sugar
{

    /**
     * All properties of model are stored here
     * @var
     */
    protected $data = [];

    /**
     * The CatalogObject
     * @var Bond\Pg\Catalog;
     */
    protected $catalog;

    /**
     * @param array Associative array of model properties
     */
    public function __construct( Catalog $catalog, array $properties = [] )
    {
        $this->catalog = $catalog;
        $this->data = $properties;
    }

    /**
     * @return array All properties of this object
     */
    public function all()
    {
        return $this->data;
    }

    /**
     * @desc Get the catalog
     * @return Bond\Pg\Catalog
     */
    public function getCatalog()
    {
        return $this->catalog;
    }

    /**
     * @return string The boring fully qualified name of this object
     */
    public function __toString()
    {
        return $this->getFullyQualifiedName();
    }

    /**
     * @desc Sugar to make this object behave like a stdClass
     * @return mixed
     */
    public function __get( $key )
    {
        if( !array_key_exists( $key, $this->data ) ) {
            throw new BadPropertyException( $key, $this );
        }
        return $this->data[$key];
    }

    /**
     * @param string property name
     * @param mixed value
     * @desc Set property on object
     */
    public function __set( $key, $value )
    {
        $this->data[$key] = $value;
    }

    /**
     * @param string property name
     * @return bool
     */
    public function __isset( $key )
    {
        return isset( $this->data[$key] );
    }

    /**
     * @param string property name
     */
    public function __unset( $key )
    {
        unset( $this->data[$key] );
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return json_encode($this->data);
    }

    /**
     * Countable interface
     * @return int
     */
    public function count()
    {
        return count($this->data);
    }

}