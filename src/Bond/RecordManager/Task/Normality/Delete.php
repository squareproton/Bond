<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\Normality;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Normality;

use Bond\Repository;

use Bond\Entity\Base;
use Bond\Entity\DataType;

use Bond\Pg;
use Bond\Sql\Raw;

class Delete extends Normality
{

    /**
     * Execute a task
     *
     * @param Pg $pg Database connection
     * @param bool TODO. Simulate task execution.
     */
    public function execute( Pg $pg, $simulate = false )
    {

        // Do we have anything to do?
        if( $this->object->isNew() || $this->object->isReadonly() ) {
            return true;
        }

        $repo = $this->recordManager->entityManager->getRepository( $this->object );
        $table = $repo->table;
        $primaryKeys = $repo->dataTypesGet( DataType::PRIMARY_KEYS );

        // extract column info
        static::extractColumnInfoFromDataTypes( $primaryKeys, $pg, $modifiers );

        // build data array
        $data = static::buildQueryDataFromEntity( $this->object, array_keys( $modifiers ), null, null, Base::INITIAL, $this );

        static::validateQueryData( $data, $modifiers );

        $query = self::buildQueryDelete(
            $pg,
            $table,
            array( $data ),
            array()
        );

        $query = new Raw( $query, $data );
        $pg->query( $query );

        $this->object->markDeleted();

        return true;

    }

}