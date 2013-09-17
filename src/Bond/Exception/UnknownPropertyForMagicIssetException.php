<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class UnknownPropertyForMagicIssetException extends \InvalidArgumentException
{
    public function __construct( $object, $property )
    {
        parent::__construct(
            sprintf(
                "Unknown magic property access for %s->__isset(`%s`)",
                get_class( $object ),
                $property
            )
        );
    }
}