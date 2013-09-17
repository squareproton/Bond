<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog;

use Bond\Sql\SqlInterface;
use Bond\Sql\QuoteInterface;

class PgIndex implements SqlInterface
{

    use Sugar;

    /**
     * {@inheritDoc}
     */
    public function parse( QuoteInterface $quoting )
    {
        return $quoting->quoteIdent( $this->getFullyQualifiedName() );
    }

    public function getFullyQualifiedName()
    {
        return "{$this->schema}.{$this->name}";
    }

}