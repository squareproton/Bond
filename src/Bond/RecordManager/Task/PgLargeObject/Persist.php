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

class Persist extends ParentTask
{

    public function execute( Pg $pg, $simulate = false )
    {

        if (!$this->object->isChanged()) {
            return true;
        }

        // IMPORTANT: pg_lo_import/export DO NOT WORK without a transaction!
        $pg->query(new Query('BEGIN'));

        if (!$oid = pg_lo_import($pg->resource->get(), $this->object->getFilePath())) {
            throw new QueryException('Unable to import PgLargeObject');
        }

        // IMPORTANT: pg_lo_import/export DO NOT WORK without a transaction!
        $pg->query(new Query('COMMIT'));

        $this->object->markPersisted($oid);

        return true;
    }

}