<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\NormalityCollection;

use Bond\Repository;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\NormalityCollection;

use Bond\Entity\Base;
use Bond\Entity\DataType;

use Bond\Pg;
use Bond\Sql\Raw;

class Delete extends NormalityCollection
{

    /**
     * Execute a task
     *
     * @param Pg $pg Database connection
     * @param bool TODO. Simulate task execution.
     */
    public function execute( Pg $pg, $simulate = false )
    {

        // only work on objects that have changed and aren't readonly
        $collection = array_filter(
            $this->object->collection,
            function($entity){
                return !$entity->isNew() and !$entity->isReadonly();
            }
        );

        if( count( $collection ) === 0 ) {
            return true;
        }

        $class = static::getClassFromCollection( $collection );

        $repo = $this->recordManager->entityManager->getRepository( $class );
        $table = $repo->table;
        $primaryKeys = $repo->dataTypesGet( DataType::PRIMARY_KEYS );

        // extract column info
        static::extractColumnInfoFromDataTypes( $primaryKeys, $pg, $modifiers );

        $data = array();
        foreach( $collection as $entity ) {

            // build data array
            $data[] = static::buildQueryDataFromEntity( $entity, array_keys( $modifiers ), null, null, Base::INITIAL, $this );

        }

        static::validateQueryDatas( $data, $modifiers );

        $query = self::buildQueryDelete(
            $pg,
            $table,
            $data,
            array()
        );

        $query = new Raw( $query, $data );
        $pg->query( $query );

        foreach( $collection as $entity ) {
            $entity->markDeleted();
        }

        return true;

    }

}