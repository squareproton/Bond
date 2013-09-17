<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container;

class PropertyMapperObjectAccess implements PropertyMapperInterface
{
    public $property;
    public function __construct( $property )
    {
        $this->property = $property;
    }
    public function get( $obj )
    {
        $property = $this->property;
        return $obj->$property;
    }
    public function set( $obj, $value )
    {
        $property = $this->property;
        $obj->$property = $value;
    }
    public function compatibilityCheck( $obj )
    {
        return is_object($obj);
    }
}