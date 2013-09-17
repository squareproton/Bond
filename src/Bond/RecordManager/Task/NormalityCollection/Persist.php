<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task\NormalityCollection;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\NormalityCollection;

use Bond\Container;
use Bond\Entity\Base;
use Bond\Entity\DataType;
use Bond\Repository;

use Bond\Pg;
use Bond\Pg\Result;
use Bond\Sql\Query;
use Bond\Sql\Raw;

class Persist extends NormalityCollection
{

    /**
     * Execute a task
     *
     * @param Pg $pg Database connection
     * @param bool TODO. Simulate task execution.
     */
    public function execute( Pg $pg, $simulate = false, array &$ignoreList = array(), $action = self::ACTION_OBJECT )
    {

        $profiler = new \Bond\Profiler( __FUNCTION__ );

        $profiler->log('start');

        $collection = array();

        if( !isset( $ignoreList[$action] ) ) {
            $ignoreList[$action] = array();
        }

        // readonly check only needs to be performed once per action
        if( $class = $this->object->class ) {

            $isReadonly = call_user_func( "{$class}::isReadonly" );
            if( $isReadonly === Base::READONLY_DISABLE || $isReadonly === Base::READONLY_EXCEPTION ) {
                return true;
            }

        }

        foreach( $this->object->collection as $entity ) {

            // Do we have anything to do?
            if(
                !$entity->isChanged() ||
                false !== array_search( $entity, $ignoreList[$action], true )
            )
            {

                continue;

            } else {

                $ignoreList[$action][] = $entity;
                $collection[] = $entity;

            }

        }

        $profiler->log('filtered');

        // Do we have anything to do?
        if( count( $collection ) === 0 ) {
            return true;
        }

        // Init repository so we've can get object information
        $repo = $collection[0]->r();
        $table = $repo->table;
        $primaryKeys = $repo->dataTypesGet( DataType::PRIMARY_KEYS );
        $dataTypes = $repo->dataTypesGet();

        $sequences = array();
        $defaults = array();

        // extract column info
        static::extractColumnInfoFromDataTypes( $dataTypes, $pg, $modifiers, $modifiersInitial, $sequences, $defaults, true );

        // chain saving
        $chainSaving = array();
        $columnNames = array_keys( $modifiers );
        $columnNamesInitial = array_keys( $modifiersInitial );
        foreach( $collection as $entity ) {

            $chainSavingTasks = static::buildChainTasks( $entity, $columnNames );

            // add chaintasks into their collections
            foreach( $chainSavingTasks as $action => $entity ) {
                if( !isset($chainSaving[$action]) ) {
                    $chainSaving[$action] = new Container();
                }
                $chainSaving[$action]->add( $entity );
            }

        }

        // chainTasks
        // how do zombie's interact with chain saving?
        foreach( $chainSaving as $action => $chainsave ) {
            if( $task = $this->recordManager->getTask( $chainsave, static::PERSIST, false ) ) {
                $task->execute( $pg, $simulate, $ignoreList, $action );
            }
        }

        $profiler->log('chainsaving analysis');

        // build data array
        $inserts = array();
        $updates = array();
        $zombies = array();

        foreach( $collection as $key => $entity ) {

            $isNew = $entity->isNew();

            if( $isNew === false and $entity->isZombie() ) {

                $zombies[] = $entity;

            } else {

                $data = static::buildQueryDataFromEntity( $entity, $columnNames, $sequences, $defaults, Base::DATA, $this );

                if( $isNew === false ) {
                    $updates[$key] = $data;
                    $updatesInitial[$key] = static::buildQueryDataFromEntity( $entity, $columnNamesInitial, $sequences, $defaults, Base::INITIAL, $this );
                } else {
                    $inserts[$key] = $data;
                }

            }

        }

        $profiler->log('build inserts / updates');

        // returning clause
        $returning = array_merge(
            array_keys( $sequences ),
            array_keys( $defaults )
        );

        if( $zombies ) {

            $zombieContainer = new Container( $zombies );
            $task = $this->recordManager->getTask( $zombieContainer, Task::DELETE );
            $task->execute( $pg, $simulate );

        }

        // Inserts
        if( $inserts ) {

            static::validateQueryDatas( $inserts, $modifiers );
            $profiler->log('insert validated');

            $sql = self::buildQueryInsert( $pg, $table, $inserts, $returning );
            $profiler->log('build query');

            // perform insert and get inserted data
            $query = new Raw( $sql );
            $result = $pg->query( $query );
            $resultData = $result->fetch( Result::FLATTEN_PREVENT | Result::TYPE_DETECT );
            $profiler->log('execution');

            // update our entities
            // mark persisted even if we don't have any data
            reset( $inserts );
            if( $returning ) {
                foreach( $resultData as $row ) {
                    list($key,) = each($inserts);
                    $collection[$key]->setDirect( $row );
                    $collection[$key]->markPersisted();
                }
            } else {
                while( list($key,) = each($inserts) ) {
                    $collection[$key]->markPersisted();
                }
            }

            $profiler->log('returning');

        }

        // Updates
        if( $updates ) {

            static::validateQueryDatas( $updates, $modifiers );
            static::validateQueryDatas( $updatesInitial, $modifiersInitial );
            $returningUpdate = $returning + $columnNamesInitial;
            $sql = self::buildQueryUpdate( $pg, $table, $primaryKeys, $updates, $updatesInitial, $returningUpdate );

            // perform update and get updated data
            $query = new Raw( $sql );
            $result = $pg->query( $query );
            $resultData = $result->fetch( Result::FLATTEN_PREVENT | Result::TYPE_DETECT );

            reset( $updates );
            if( $returningUpdate ) {
                foreach( $resultData as $row ) {
                    list($key,) = each($updates);
                    $collection[$key]->setDirect( $row );
                    $collection[$key]->markPersisted();
                }
            } else {
                while( list($key,) = each($updates) ) {
                    $collection[$key]->markPersisted();
                }
            }

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
        $repository = $this->recordManager->entityManager->getRepository( $this->object->class );
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