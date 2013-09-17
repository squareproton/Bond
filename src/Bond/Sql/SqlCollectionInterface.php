<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

// Class' that implement this interface cast themselves to 'set' constructs in sql.
// At present this is just 'IN' and 'VALUES' clauses but will likely be extended to arrays and others
//
// Class' that implement this interface are responsible for outputting SQL injection proof
// sql fragments. __NO__ further escaping is done.

interface SqlCollectionInterface extends SqlInterface
{
}