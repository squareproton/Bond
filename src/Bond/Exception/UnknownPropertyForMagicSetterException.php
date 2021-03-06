<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class UnknownPropertyForMagicSetterException extends \InvalidArgumentException
{
    public function __construct( $object, $property )
    {
        parent::__construct(
            sprintf(
                "Unknown magic property assignment for %s->__set(`%s`)",
                get_class( $object ),
                $property
            )
        );
    }
}