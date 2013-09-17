<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class BadPropertyException extends \Exception
{

    public $property;
    public $obj;

    public function __construct( $property, $obj, $message = '' )
    {
        $this->property = $property;
        $this->obj = $obj;
        $this->message = sprintf(
            "Property `%s` doesn't exist on `%s`. %s",
            $property,
            get_class( $obj ),
            $message
        );
    }
}