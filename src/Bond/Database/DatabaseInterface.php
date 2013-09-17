<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database;

use Bond\Database\ResourceInterface;
use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

// Objects that expose a database interface
interface DatabaseInterface extends QuoteInterface
{
    public function __construct( $resource, $name = null );
    public function query( SqlInterface $sql, $options = 0 );
    public function version();
}