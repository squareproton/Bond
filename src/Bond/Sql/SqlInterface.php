<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

// Specification for objects that wish to provide their own quoting
interface SqlInterface
{
    public function parse( QuoteInterface $quoteInterface );
}