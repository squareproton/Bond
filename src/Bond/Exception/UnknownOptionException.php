<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class UnknownOptionException extends \Exception
{
    public function __construct( $option, array $allowedOptions )
    {
        parent::__construct(
            sprintf(
                "Unknown option `%s`. Allowed options are one of %s",
                $option,
                implode( ", ", $allowedOptions )
            )
        );
    }
}