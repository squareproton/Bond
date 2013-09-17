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
use Bond\Container;

use Bond\Pg;
use Bond\Sql\Query;
use Bond\Sql\Raw;
use Bond\Pg\Result;

class Persist extends Normality
{

    /**
     * Execute a task
     *
     * @param Pg $pg Database connection
     * @param bool TODO. Simulate task execution.
     */
    public function execute( Pg $pg, $simulate = false, array &$ignoreList = array(), $action = self::ACTION_OBJECT )
    {

        if( !isset( $ignoreList[$action] ) ) {
            $ignoreList[$action] = array();
        }

        $readonly = $this->object->isReadonly();

        // Do we have anything to do?
        if( false !== array_search( $this->object, $ignoreList[$action], true ) ) {

            return true;

        // can't save anuthing off this object
        } elseif( $readonly === Base::READONLY_EXCEPTION || $readonly === Base::READONLY_DISABLE ) {

            return true;

        } elseif( !$this->object->isChanged() ) {

            $this->additionalSavingTasks( $pg, $simulate, $ignoreList, $action );

        } else {

            $ignoreList[$action][] = $this->object;

        }

        $repo = $this->recordManager->entityManager->getRepository($this->object);
        $table = $repo->table;
        $primaryKeys = $repo->dataTypesGet( DataType::PRIMARY_KEYS );

        // extract column info
        static::extractColumnInfoFromDataTypes( $repo->dataTypesGet(), $pg, $modifiers, $modifiersInitial, $sequences, $defaults, true );
        $columnNames = array_keys( $modifiers );
        $columnNamesInitial = array_keys( $modifiersInitial );

        $chainSavingTasks = static::buildChainTasks( $this->object, $columnNames );

        // Chain saving
        foreach( $chainSavingTasks as $taskAction => $entity ) {

            if( $task = $this->recordManager->getTask( $entity, Task::PERSIST, false ) ) {
                $response = $task->execute( $pg, $simulate, $ignoreList, $taskAction );
//                    var_dump( $response ); print_r( $object );
            }

        }

        // build data array
        $data = static::buildQueryDataFromEntity( $this->object, $columnNames, $sequences, $defaults, Base::DATA, $this );
        $dataInitial = static::buildQueryDataFromEntity( $this->object, $columnNamesInitial, $sequences, $defaults, Base::INITIAL, $this );

        static::validateQueryData( $data, $modifiers );
        static::validateQueryData( $dataInitial, $modifiersInitial );

        $returning = array_merge(
            array_keys( $sequences ),
            array_keys( $defaults ),
            $columnNamesInitial
        );

        $isNew = $repo->isNew( $this->object );

        // cull zombies!
        if( $isNew === false and $this->object->isZombie() ) {

            if( $task = $this->recordManager->getTask( $this->object, Task::DELETE, false ) ) {
                $response = $task->execute( $pg, $simulate );
            }

        } else {

            $query = $isNew === false ?
                static::buildQueryUpdate( $pg, $table, $primaryKeys, array( $data ), array( $dataInitial ), $returning ) :
                static::buildQueryInsert( $pg, $table, array( $data ), $returning )
            ;

            // echo "{$query}\n";

            $query = new Raw( $query );
            $result = $pg->query( $query );

            $resultData = $result->fetch( Result::FETCH_SINGLE | Result::FLATTEN_PREVENT );

            $this->object->setDirect( $resultData );
            $this->object->markPersisted();

        }

        // any additional saving tasks?
        $this->additionalSavingTasks( $pg, $simulate, $ignoreList, $action );

        return true;

    }

    /**
     * Execute additional saving tasks.
     * @return true
     */
    protected function additionalSavingTasks( Pg $pg, $simulate = false, array &$ignoreList = array(), $action = self::ACTION_OBJECT )
    {

        // additional saving tasks?
        $repository = $this->object->r();
        $normality = $repository->normality;

        // links
        if( isset( $normality['persist']['links'] ) ) {
            foreach( $normality['persist']['links'] as $link ) {

                $links = $repository->linksGet( $this->object, $link, Repository::CHANGED + Base::INITIAL );

                // save links
                if( $task = $this->recordManager->getTask( $links, Task::PERSIST, false ) ) {
                    $task->execute( $pg, $simulate, $ignoreList, self::ACTION_OBJECT );
                }

            }
        }

        // references
        if( isset( $normality['persist']['references'] ) ) {
            foreach( $normality['persist']['references'] as $reference ) {

                $references = $repository->referencesGet( $this->object, $reference, Repository::CHANGED );

                // save links
                if( $references and $task = $this->recordManager->getTask( $references, Task::PERSIST, false ) ) {
                    $task->execute( $pg, $simulate, $ignoreList, self::ACTION_OBJECT );
                }

            }
        }

        return true;

    }

}