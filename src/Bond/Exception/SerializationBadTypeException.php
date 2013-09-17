<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class SerializationBadTypeException extends \Exception
{
    public function __construct( $passedClass, $unserializeClass )
    {
        parent::__construct(
            sprintf(
                "Attempting to unserialize `%s` via `%s`->unserialize() won't work.",
                $passedClass,
                $unserializeClass
            )
        );
    }
}