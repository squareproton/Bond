<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database;

// Specification for objects that wish to convert a string represenation of a postgres datatype into a useful php equivalent
interface TypeConversionInterface
{
    public function __invoke( $input );
}