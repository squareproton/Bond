<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\PgLargeObject;

use Bond\RecordManager\Task\PgLargeObject as ParentTask;

use Bond\Pg;
use Bond\Pg\Connection;
use Bond\Sql\Query;
use Bond\Pg\Exception\Query as QueryException;

use Bond\Entity\Types\PgLargeObject;

class Delete extends ParentTask
{

    public function execute( Pg $pg, $simulate = false )
    {

        if ( $this->object->isNew() ) {
            return true;
        }

        // IMPORTANT: pg_lo fn's don't work outside a transaction
        $pg->query(new Query('BEGIN'));

        if (!$response = pg_lo_unlink(  $pg->resource->get(), $this->object->getOid() ) ) {
            throw new QueryException("Unable to delete PgLargeObject {$this->object->getOid()}");
        }

        // IMPORTANT: pg_lo_import/export DO NOT WORK without a transaction!
        $pg->query(new Query('COMMIT'));

        $this->object->markDeleted();

        return true;
    }

}