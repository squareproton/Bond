<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database\TypeConversion;

use Bond\Database\TypeConversion;

/**
 * Shim for the old legacy static callback system we had before the move to a much better parser
 */
class NullSafeCallback extends TypeConversion
{

    private $callback;

    public function __construct( $type, $callback )
    {
        parent::__construct($type);
        $this->callback = $callback;
    }

    /**
     * actually perform the conversion
     */
    public function __invoke($input)
    {
        return null === $input
            ? null
            : call_user_func($this->callback, $input)
            ;
    }

}