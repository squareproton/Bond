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

class PropertyMapperEntityInitial implements PropertyMapperInterface
{
    public $property;
    public function __construct( $property )
    {
        $this->property = $property;
    }
    public function get( $obj )
    {
        return $obj->get($this->property, null, $_ = Base::INITIAL );
    }
    public function set( $obj, $value )
    {
        throw new Exception("Can't set a Base::INITIAL property on a entity. It doesn't make any sense.");
    }
    public function compatibilityCheck( $obj )
    {
        return is_object($obj) and $obj instanceof Base;
    }
}