<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database;

use Bond\Database\DatabaseInterface;
use Bond\Sql\SqlInterface;

interface ResultInterface
{

    public function __construct( $resource, DatabaseInterface $db, SqlInterface $query, array $timings = array() );

    public function setFetchOptions( $options );
    public function getFetchOptions();

    public function fetch( $options, $keyResultsByColumn );

    public function count();
    public function numRows();
    public function numFields();
    public function numAffectedRows();

}