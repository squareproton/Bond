<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Sql\SqlInterface;
use Bond\Sql\QuoteInterface;

class Sql implements SqlInterface
{

    /**
     * Sql
     * @var string $value
     */
    protected $value;

    /**
     * The sql you wish to set directly
     * @param string $value
     */
    public function __construct( $value )
    {
        $this->value = $value;
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quote )
    {
        return $this->value;
    }

}