<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Repository;

use Bond\Container;
use Bond\Container\FindFilterComponentFactory;

use Bond\Entity\Base;
use Bond\Entity\DataType;

use Bond\Profiler;

use Bond\Repository;
use Bond\Repository\Exception\EntityStateUndeterminableException;
use Bond\Repository\EntityNotCompatibleWithRepositoryException;
use Bond\Repository\Exception\MultitonKeyCollisionException;

use Bond\Set;

/**
 * Description of Repository
 * @author pete
 */
abstract class Multiton extends Repository
{

    /**
     * Multiton cache. Persisted instances
     * @var array
     */
    protected $instancesPersisted = array();

    /**
     * Multiton cache. Unpersisted instances
     * @var array
     */
    protected $instancesUnpersisted = array();

    /**
     * Ensure garbage collection won't be a problem
     * To disable automatic garbage collection set to null.
     * @var int|null
     */
    protected $instancesMaxAllowed = 10000;

    /**
     * Add a entity into the multiton array
     *
     * @param Entity\Base
     * @param mixed If successful attachment the location of the new attachment
     *
     * @return bool. Successful attachment?
     */
    public function attach( Base $entity, &$restingPlace = null )
    {

        if( !$this->makeableSafetyCheck() ) {
            return null;
        }

        // check type
        if( !is_a( $entity, $this->entityClass ) ) {
            throw new EntityNotCompatibleWithRepositoryException( $entity, $this );
        }

        // At the moment something is new if it doesn't have a key set.
        // This is still a work in progress
        if( $key = $entity->keyGet( $entity ) ) {

            // check we've not got a collision already
            if( isset( $this->instancesPersisted[$key] ) ) {
                throw new \RuntimeException( "Repository entity merging not yet coded (but I'm not exactly sure why you need this). You've probably got a logic bug somewhere. What you are asking is, well, odd.");
            }

            $this->instancesPersisted[$key] = $entity;
            $restingPlace = self::PERSISTED;

        } else {

            // debugging check to find where something is being double attached
            if( false !== array_search( $entity, $this->instancesUnpersisted, true ) ) {
                d_pr( sane_debug_backtrace() );
                die();
            }

            $this->instancesUnpersisted[] = $entity;
            $restingPlace = self::UNPERSISTED;

        }

        return true;

    }

    /**
     * Is in multiton store.
     * @return bool
     */
    public function isAttached( Base $entity )
    {
        return false !== array_search( $entity, $this->instancesPersisted, true ) ||
               false !== array_search( $entity, $this->instancesUnpersisted, true );
    }

    /**
     * Detach a entity from the multiton
     * @param Entity\Base
     * @return bool Successful detachement?
     */
    public function detach( Base $entity, &$detachedFrom = null )
    {

        $detachedFrom = null;

        foreach( array( 'Persisted', 'Unpersisted') as $type ) {

            $cache = "instances{$type}";
            $cache =& $this->$cache;

            if( false !== $key = array_search( $entity, $cache, true ) ) {
                unset( $cache[$key] );
                $detachedFrom = $this->constantGet( strtoupper( $type ) );
                return true;
            }

            unset( $cache );

        }

        return false;

    }

    /**
     * A entity's key has changed. If it is in the repository this'll need rekeying
     * @param Base $entity
     */
    public function rekey( Base $entity )
    {

        if( false !== $key = array_search( $entity, $this->instancesPersisted, true ) ) {

            $newKey = $entity->keyGet( $entity );

            if( isset( $this->instancesPersisted[$newKey] ) and $entity !== $this->instancesPersisted[$newKey] ) {
                throw new \RuntimeException( "Different object already exists with this same `key`. Clear collision." );
            }

            unset( $this->instancesPersisted[$key] );
            $this->instancesPersisted[$newKey] = $entity;
            return true;

        }

        return false;

    }

    /**
     * Multiton. Fuck yeah!
     *
     * @param $key scalar. This key will be used to key the multiton array and instance the multiton.
     * @param bool $disableLateLoading
     * @param bool $cache default false. Is is now possible to create a object that doesn't exist in the multion.
     */
    public function find( $key, $disableLateLoading = null, $cache = true )
    {

        // $disableLateLoading default value management
        if( !is_bool( $disableLateLoading ) ) {
            $disableLateLoading = false;
        }

        // have we switched off the multiton?
        if( !$cache ) {
            return parent::find( $key, $disableLateLoading );
        }

        // determine the multitonKey
        if( is_null( $key ) ) {
            return null;
        } elseif( is_scalar( $key ) ) {
            $multitonKey = $key;
        } elseif( is_array( $key ) ) {
            $multitonKey = call_user_func( "{$this->entityClass}::keyGet", $key );
        } else {
            return null;
        }

        // do we have this?
        if( !isset( $this->instancesPersisted[$multitonKey] ) ) {

            $obj = parent::find( $key, $disableLateLoading );

            // not got anything?
            if( !$obj ) {
                return null;
            }

            $this->garbageCollect();
            $this->instancesPersisted[$multitonKey] = $obj;

        // disable late loading
        } elseif( $disableLateLoading ) {

            $this->instancesPersisted[$multitonKey]->load();

        }

        return $this->instancesPersisted[$multitonKey];

    }

    /**
     * Lookup a entity based on it's 'primary key' or key column when passed a set.
     * This will always return a Container
     * @return Bond\Container
     */
    public function findBySet( Set $keySet )
    {

        // the simplest thing that possibly works
        // there're a lot of fairly easy performance gains to doing this differently.
        // this can be speeded up lots. Think interval intersection with value explosion.

        $multiton = $this->makeNewContainer();

        // persisted
        foreach( $this->instancesPersisted as $key => $entity ) {
            if( $keySet->contains( $key ) ) {
                $multiton->add( $entity );
            }
        }

        // unpersisted
        foreach( $this->instancesUnpersisted as $entity ) {
            if( $keySet->contains( $entity->keyGet( $entity ) ) ) {
                $multiton->add( $entity );
            }
        }

        $persisted = parent::findBySet( $keySet );

        return $this->makeNewContainer()->add( $multiton, $persisted );

    }

    /**
     * {@inheritDoc}
     * @param string $qty
     * @param array $filterComponents
     * @param int Bitmask fo class constants
     * @return Bond\Container
     */
    public function findByFilterComponents( $qty, array $filterComponents, $source = null )
    {

        $source = $this->findByFilterComponentsSourceSetup( $source );

        $output = $this->makeNewContainer();

        $profiler = new Profiler( __FUNCTION__ );
        $profiler->log("setup");

        if( $source & self::UNPERSISTED ) {
            $output->add( $this->findByFilterComponentsMultiton( $filterComponents, $source ) );
        }
        $profiler->log("multiton");

        if( $source & self::PERSISTED ) {

            $cannotMatch = function ($entity) {
                return $entity instanceof Base ? $entity->isNew() : false;
            };

            // If all of the filter components can't match - that is return true - there isn't any point in going to the db
            $checkDatabase = !\Bond\array_check(
                function( $filterComponent ) use ( $cannotMatch ) {
                    return $filterComponent->getCannotMatch( $cannotMatch );
                },
                $filterComponents,
                false // if you don't have any filtering you always want to check the db
            );

            if( $checkDatabase ) {
                $output->add( $this->findByFilterComponentsDatabase( $filterComponents, $source ) );
            }

        }
        $profiler->log("database");

        return $this->findByFilterComponentsFormatReturnValue( $qty, $output );

    }

    /**
     * Find entities in our multiton instances array(s) which match the following critera
     *
     * @param array $filterComponents
     * @param int Source bitmask
     * @return object|Bond\Container
     */
    protected function findByFilterComponentsMultiton( array $filterComponents, $source )
    {

        $multitonEntities = $this->makeNewContainer();

        if( $source & self::PERSISTED ) {
            $multitonEntities->add( $this->persistedGet() );
        }

        if( $source & self::UNPERSISTED ) {
            $multitonEntities->add( $this->unpersistedGet() );
        }

        $isChangedFilterCallback = $this->getFilterByIsChangedCallback($source);
        $multitonEntities = $multitonEntities->filter($isChangedFilterCallback);

        return $multitonEntities->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            $filterComponents,
            $source
        );

    }

    /**
     * Make a new entity
     * @inheritDoc()
     */
    public function make( array $data = null )
    {
        $obj = parent::make( $data );
        $this->instancesUnpersisted[] = $obj;
        return $obj;
    }

    /**
     * Entry point for objects where you've got the data array and you wish to instantiate
     * a object and add to the cache.
     *
     * @param array $data
     * @return Bond\Entity
     */
    public function initByData( array $data = null )
    {

        if( !$data ) {
            return null;
        }

        // table inheritance - ghetto?
        /*
        if( isset( $data['tableoid'] ) ) {
            if( $data['tableoid'] != static::OID ) {
                $repo = Repository::init( $data['tableoid'] );
                return $repo->initByData( $data );
            }
            unset( $data['tableoid'] );
        }
        */

        $key = call_user_func(
            array( $this->entityClass, 'keyGet' ),
            $data
        );

        // is this already in the cache? Are we initalising something we've already loaded?
        if( isset( $this->instancesPersisted[$key] ) ) {

            // Late loading? Don't need this anymore.
            $obj = $this->instancesPersisted[$key];
            $obj->__construct( $data );
            return $obj;

        }

        $obj = parent::initByData($data);
        $this->instancesPersisted[$key] = $obj;

        return $obj;

    }

    /**
     * Is this Entity 'new'? A new Entity exists in the $instancesUpersisted array
     * @param Set a new isNew() state
     * @return bool
     */
    public function isNew( Base $entity, $state = null )
    {

        if( is_bool( $state ) ) {

            // mark entity as new
            if( $state ) {

                // remove from instances persisted cache if exists there.
                // This could also be done by self::garbageCollect but this is a lighter and doesn't need to check all cases
                if( false !== $_key = array_search( $entity, $this->instancesPersisted, true ) ) {
                    unset( $this->instancesPersisted[$_key] );
                }

                if( false !== array_search( $entity, $this->instancesUnpersisted, true ) ) {
                    d_pr( sane_debug_backtrace() );
                    die();
                }

                $this->instancesUnpersisted[] = $entity;
                return true;

            // mark entity as persisted
            } else {

                $key = $entity->keyGet( $entity );

                if( is_null( $key ) ) {
                    $this->detach( $entity );
                    return false;
                }

                // remove from instances unpersisted cache if exists there.
                // This could also be done by self::garbageCollect but this is a lighter and doesn't need to check all cases
                if( false !== $_key = array_search( $entity, $this->instancesUnpersisted, true ) ) {
                    unset( $this->instancesUnpersisted[$_key] );
                }

                if( isset( $this->instancesPersisted[$key] ) and $this->instancesPersisted[$key] !== $entity ) {
                    throw new MultitonKeyCollisionException( "Unable to set new. A {$this->entity} with key `{$key}` already exists in static::\$instancesPersisted" );
                }

                $this->instancesPersisted[$key] = $entity;

                return false;

            }

        }

        $isUnpersisted = false !== array_search( $entity, $this->instancesUnpersisted, true );

        $isCached = $this->isCached( $entity );
        if( $isUnpersisted and $isCached ) {

            // debuggin' shit
            // d_pr( "Entity " . __CLASS__ . "has entity's both persisted and unpersisted." );
            // d_vd( $entity );
            // die();
            throw new \LogicException( "Something is very wrong. This entity is in both the persisted and unpersisted respository caches. This isn't right." );

        } elseif( !$isUnpersisted and !$isCached ) {

            // Pete. This original behaviour caused problems with double attachments of entities when $entity->isNew() was called
            // before a entity was attached to the multiton array. I think I'm going to depreciate this unexpected, magical behaviour.
            // I don't think this is exception worthy. Instead this deserves a new, third, state -> null to singify not cached. Don't know.

            return null;

            // original behaviour below
            // $this->attach( $entity, $restingPlace );
            //
            // switch( $restingPlace ) {
            //    case self::PERSISTED:
            //        return false;
            //    case self::UNPERSISTED:
            //        return true;
            //    default:
            //        throw new \RuntimeException("Bad response from repository->attach()");
            // }

            // not sure if this should be a exception
            throw new EntityStateUndeterminableException( "This entity didn't come from the repository. Its isNew() state cannot be determined." );
        }

        return $isUnpersisted;

    }

    /**
     * Is this object in the multiton cache
     * @return bool;
     */
    public function isCached( Base $entity, $source = self::PERSISTED )
    {
        switch( $source ) {
            case self::PERSISTED:
                return false !== array_search( $entity, $this->instancesPersisted, true );
            case self::UNPERSISTED:
                return false !== array_search( $entity, $this->instancesUnpersisted, true );
            case self::ALL:
                return $this->isCached( $entity, self::PERSISTED ) || $this->isCached( $entity, self::UNPERSISTED );
        }
        throw new \InvalidArgumentException("Bad source `{$source}` passed to " . __FUNCTION__);
    }

    /**
     * Returns the number of cached items
     * @return int Number of instances stored
     */
    public function cacheSize( $type = self::PERSISTED )
    {
        switch( $type ) {
            case self::PERSISTED:
                return count( $this->instancesPersisted );
            case self::UNPERSISTED:
                return count( $this->instancesUnpersisted );
            case self::ALL:
                return $this->cacheSize( self::PERSISTED ) + $this->cacheSize( self::UNPERSISTED );
        }
        throw new \InvalidArgumentException("Bad source `{$source}` passed to " . __FUNCTION__);
    }

    /**
     * Invalidate cache. Returns multiton to it's vanilla state. Required for unit testing.
     */
    public function cacheInvalidate( $type = self::ALL )
    {
        switch( $type ) {
            case self::PERSISTED:
                $return = count( $this->instancesPersisted );
                $this->instancesPersisted = array();
                return $return;
            case self::UNPERSISTED:
                $return = count( $this->instancesUnpersisted );
                $this->instancesUnpersisted = array();
                return $return;
            case self::ALL:
                $return = count( $this->instancesPersisted ) + count( $this->instancesUnpersisted );
                $this->instancesPersisted = array();
                $this->instancesUnpersisted = array();
                return $return;
        }
        throw new \InvalidArgumentException("Bad source `{$source}` passed to " . __FUNCTION__);
    }

    /**
     * Public access to the multiton persisted cache
     * @return Bond\Container
     */
    public function persistedGet()
    {
        return $this->makeNewContainer()->add( $this->instancesPersisted );
    }

    /**
     * Public access to the multiton unpersisted cache
     * @return Bond\Container
     */
    public function unpersistedGet()
    {
        return $this->makeNewContainer()->add( $this->instancesUnpersisted );
    }

    /**
     * Access to $instancesMaxAllowed
     * @return int
     *
     */
    public function __get( $key )
    {
        switch( $key ) {
            case 'instancesMaxAllowed':
                return $this->$key;
        }
        return parent::__get( $key );
    }

    /**
     * Removes a object from the multiton if
     *
     *  1. You pass its `key` (ie, the key that you would have used to instantiate it with get()
     *  2. You pass it the object (includes objects in $this->instancesUnpersisted
     *  3. You don't pass anything and there are > $this->instancesPersisted max_allowed persisted instances of this object in that have _not_ been changed
     *     This does not gaurentee that you will always have < $this->instancesPersisted records stored.
     *
     * @param mixed $keyOrObject
     * @return int number of objects removed from the multiton
     */
    public function garbageCollect( $keyOrObject = null )
    {

        // we're going to be doing the $this->instancesPersisted_max_allowed check first
        if( $keyOrObject == null ) {

            // auto garbage collection is disabled.
            if( !is_int( $this->instancesMaxAllowed ) ) {
                return 0;
            }

            $removed = 0;

            reset( $this->instancesPersisted );

            // iterate over the instances starting oldest first while we've got too many
            while( count( $this->instancesPersisted ) >= $this->instancesMaxAllowed && list( $key, $obj ) = each( $this->instancesPersisted ) ) {

                if( !$obj->isChanged() ) {

                    unset( $this->instancesPersisted[$key] );
                    $removed++;

                }

            }

            return $removed;

        }

        if( is_object( $keyOrObject ) ) {

            foreach( $this->instancesPersisted as $key => $obj ) {

                if( $keyOrObject === $obj ) {

                    if( $obj->isChanged() ) {
                        return 0;
                    } else {
                        unset( $this->instancesPersisted[$key] );
                        return 1;
                    }

                }

            }

            // remove from static unpersisted
            if( false !== $_key = array_search( $keyOrObject, $this->instancesUnpersisted, true ) ) {
                unset( $this->instancesUnpersisted[$_key] );
            }

        } elseif( is_scalar( $keyOrObject ) && isset( $this->instancesPersisted[$keyOrObject] ) ) {

            if( $this->instancesPersisted[$keyOrObject]->isChanged() ) {
                return 0;
            } else {
                unset( $this->instancesPersisted[$keyOrObject] );
                return 1;
            }

        }

        return 0;

    }

}