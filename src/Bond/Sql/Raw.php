<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

use Bond\MagicGetter;
use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

/**
 * Manages SQL queries which have been generated in such a way as to need no escaping.
 * This object doesn't incur the overhead of a regex and so is faster but is potentially unsafe!!
 * Primarily used by the record manager which not only produces vast queries but manages its own escaping.
 */
class Raw implements SqlInterface
{

    Use MagicGetter;

    private $sql;

    public function __construct( $sql )
    {
        $this->sql = (string) $sql;
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quote )
    {
        return $this->sql;
    }

}