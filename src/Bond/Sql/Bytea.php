<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

use Bond\Sql;
use Bond\Sql\Query;
use Bond\Sql\QuoteInterface;

class Bytea extends Sql
{

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoteInterface )
    {
        return $quoteInterface->quoteBytea( $this->value );
    }

}