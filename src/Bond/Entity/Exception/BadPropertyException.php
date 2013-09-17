<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Exception;

use Bond\Entity\Base;

class BadPropertyException extends EntityException
{

    public $property;
    public $obj;
    public $message;

    public function __construct( $property, Base $entity, $message = null)
    {
        $this->property = $property;
        $this->obj = $entity;
        $this->message = sprintf(
            "Property `%s` doesn't exist on entity `%s`. %s",
            $property,
            get_class( $entity ),
            $message
        );
    }
}