<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity;

use Bond\Container\PropertyMapperInterface;
use Bond\Entity\Base;

class PropertyMapperEntityData implements PropertyMapperInterface
{
    public $property;
    public function __construct( $property )
    {
        $this->property = $property;
    }
    public function get( $obj )
    {
        return $obj->get($this->property, null, $_ = Base::DATA );
    }
    public function set( $obj, $value )
    {
        return $obj->set($this->property, $value );
    }
    public function compatibilityCheck( $obj )
    {
        return is_object($obj) and $obj instanceof Base;
    }
}