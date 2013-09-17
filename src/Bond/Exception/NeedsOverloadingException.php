<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class NeedsOverloadingException extends \Exception
{
    public function __construct( $obj, $fn )
    {
        parent::__construct(
            sprintf(
                "The %s->%s() needs overloading.",
                get_class( $obj ),
                $fn
            )
        );
    }
}