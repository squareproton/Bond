<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity;

use Bond\Exception\BadJSON;
use Bond\Entity\Exception\EntityReferenceException;
use Bond\Entity\Exception\ContainerException;
use Bond\Entity\Exception\EntityLinkException;

use Bond\Repository;
use Bond\Entity\Base;

use Bond\Entity\Types\DateTime;
use Bond\Entity\Types\DateRange;
use Bond\Entity\Types\DateInterval;
use Bond\Entity\Types\Hstore;
use Bond\Entity\Types\Inet;
use Bond\Entity\Types\Json;
use Bond\Entity\Types\Oid;
use Bond\Entity\Types\PgLargeObject;
use Bond\Entity\Types\StockState;

/**
 * Helper methods for entitys.
 * Collection of callbacks that make certain tasks easy.
 *
 * Think, datetime handlers.
 *        entityManagement, links, references
 *
 */
class StaticMethods
{

    /**
     * Get datetime from Entity
     * @param DateTime $dateTime
     * @return DateTime
     */
    public static function get_DateTime( &$dateTime )
    {
        if( $dateTime instanceof DateTime ) {
            return $dateTime;
        }
        return !is_null($dateTime)
            ? new DateTime( $dateTime )
            : null
            ;
    }

    /**
     * Set entity datetime
     * @param DateTime $dateTime
     * @return DateTime
     */
    public static function set_DateTime( $dateTime )
    {
        if( $dateTime instanceof DateTime ) {
            return $dateTime;
        }
        return new DateTime( $dateTime );
    }

    /**
     * Get datetime from Entity
     * @param DateRange $dateRange
     * @return DateRange
     */
    public static function get_DateRange( &$dateRange )
    {
        if( $dateRange instanceof DateRange ) {
            return $dateRange;
        }
        return !is_null($dateRange)
            ? new DateRange( $dateRange )
            : null
            ;
    }

    /**
     * Set entity datetime
     * @param DateRange $dateRange
     * @return DateRange
     */
    public static function set_DateRange( $dateRange )
    {
        if( $dateRange instanceof DateRange ) {
            return $dateRange;
        } elseif ( is_string($dateRange) ) {
            return DateRange::makeFromString($dateRange);
        }
        return new DateRange( $dateRange );
    }

    /**
     * Get inet from Entity
     * @param Inet $dateTime
     * @return Inet
     */
    public static function get_Inet( &$inet )
    {
        if( $inet instanceof Inet ) {
            return $inet;
        }
        return !is_null($inet)
            ? new Inet($inet)
            : null
            ;
    }

    /**
     * Set entity datetime
     * @param Inet $inet
     * @return Inet
     */
    public static function set_Inet( $inet )
    {
        if( $inet instanceof Inet ) {
            return $inet;
        }
        try {
            $inet = new Inet( $inet );
            return $inet;
        } catch ( \InvalidArgumentException $e ) {
            return null;
        }
    }

    /**
     * Get hstore from Entity
     * @param HStore $dateTime
     * @return Hstore
     */
    public static function get_Hstore( &$store )
    {
        if( $store instanceof Hstore ) {
            return $store;
        }
        return !is_null($store)
            ? new Hstore($store)
            : null
            ;
    }

    /**
     * Set entity Hstore
     * @param Hstore $store
     * @return Hstore
     */
    public static function set_Hstore( $store )
    {
        if( $store instanceof Hstore ) {
            return $store;
        }
        try {
            $store = new Hstore( $store );
            return $store;
        } catch ( \InvalidArgumentException $e ) {
            return null;
        }
    }

    /**
     * Get Json from Entity
     * @param mixed $json
     * @return Json
     */
    public static function get_Json( &$json )
    {
        if( $json instanceof Json ) {
            return $json;
        }
        return !is_null($json)
            ? new Json($json)
            : null
            ;
    }

    /**
     * Set entity json property
     * @param mixed $json
     * @return Json
     */
    public static function set_Json( $json, $inputValidate )
    {
        if( $json instanceof Json ) {
            return $json;
        } elseif( null === $json ) {
            return null;
        } elseif ( !is_string($json) ) {
            return Json::makeFromObject( $json );
        }

        return new Json( $json, $inputValidate );
    }

    /**
     * Get StockState from Entity
     * @param StockState $state
     * @return StockState
     */
    public static function get_StockState( &$state )
    {
        if( $state instanceof StockState or null === $state ) {
            $state = StockState::makeFromString( $state );
        }
        return $state;
    }

    /**
     * Set entity StockState
     * @param StockState $state
     * @return StockState
     */
    public static function set_StockState( $state )
    {
        if( $state instanceof StockState or null === $state ) {
            $state = StockState::makeFromString( $state );
        }
        return $state;
    }

    /**
     * Set a entity's references
     * @param Bond\Entity\Base $entity
     * @param string $name
     * @param Container|Base|Null $value
     *
     * @return int The number of objects affected by this change.
     */
    public static function set_references( Base $entity, $name, $value = null )
    {

        $repository = $entity->r();
        if( !$repository->hasReference( $name, $detail ) ) {
            throw new EntityReferenceException("Bad reference `{$name}`");
        }

        // get existing references
        $existing = $repository->referencesGet( $entity, $name );

        $numObjectsChanged = 0;

        // container operations?
        if( $detail[2] ) {

            if( $value === null ) {

                $value = new Container();

            } elseif ( !( $value instanceof Container ) ) {
                throw new ContainerException(
                    sprintf(
                        "Reference is 1-many. You should be using containers. Passed %s",
                        print_r( $value, true )
                    )
                );
            }

            $unassociate = $existing
                ->diff( $value )
                ->each( Container::generateSetPropertyClosure( $detail[1], null ) );

            $numObjectsChanged += $unassociate->count();

            $new = $value
                ->diff( $existing )
                ->each( Container::generateSetPropertyClosure( $detail[1], $entity ) );

            $numObjectsChanged += $new->count();

        } else {

            // entity operation
            if( $entity !== $value ) {

                if( $existing ) {
                     $existing->set( $detail[1], null );
                     $numObjectsChanged++;
                }

                // add new association
                if( $value ) {
                    $value->set( $detail[1], $entity );
                     $numObjectsChanged++;
                }

            }

        }

        return $numObjectsChanged;

    }

    /**
     * Set Entity links
     * @param Bond\Entity\Base $entity
     * @param string $link String descriptor of link. Eg, ItemLinkFile
     * @param Bond\Entity\Container $new
     *
     * @return int The number of objects affected by this change.
     */
    public static function set_links( Base $entity, $link, Container $new = null )
    {

        $repository = $entity->r();
        if( !$repository->hasLink( $link, $detail ) ) {
            throw new EntityLinkException("Bad link `{$link}`");
        }

        if( is_null( $new ) ) {
            $new = new Container();
        }

        // check container is of the correct type
        $class = $new->classGet();
        $classUnqualified = get_unqualified_class( $class );
        if( $class and !in_array( $classUnqualified, $detail->foreignEntities ) ) {
            $foreignEntities = implode( ', ', $detail->foreignEntities );
            throw new EntityLinkException(
                "Passed container is of type `{$classUnqualified}` and we need one of type(s) {$foreignEntities}"
            );
        }

        // Build array key'd, $existing, like so
        // array(
        //     spl_obj_hash( 'link' ) => spl_obj_hash( 'entity' )
        // )

        $existingLinks = $repository->linksGet( $entity, $link, $classUnqualified, null, false );

        $existing = array();
        foreach( $existingLinks as $splHashLink => $link ) {
            $existing[$splHashLink] = spl_object_hash( $link[$detail->refForeign[0]] );
        }

        $newKeys = array_keys( $new->collection );

        $newKeys = array();
        $ordering = array(); $n = 0;
        foreach( $new as $splHashEntity => $_ ) {
            $newKeys[] = $splHashEntity;
            $ordering[$splHashEntity] = $n++;
        }

        // operations
        $toRemove = array_diff( $existing, $newKeys );
        $toAdd = array_diff( $newKeys, $existing );
        $unchanged = array_intersect( $existing, $newKeys );

        //
        $numModified = 0;

        // remove
        while( list($splHashLink,) = each( $toRemove ) ) {
            $existingLinks[$splHashLink][$detail->refSource[1]] = null;
            $existingLinks[$splHashLink][$detail->refForeign[0]] = null;
            $numModified++;
        }

        // add
        $linkRepo = Repository::init( $link );
        foreach( $toAdd as $key ) {

            $link = $linkRepo->make();
            $link[$detail->refSource[1]] = $entity;
            $link[$detail->refForeign[0]] = $new[$key];

            if( isset( $detail->sortColumn ) ) {
                $link[$detail->sortColumn] = $ordering[$key];
            }

            $numModified++;

        }

        if( isset( $detail->sortColumn ) ) {
            foreach( $unchanged as $linkKey => $entityKey ) {
                $numModified += $existingLinks[$linkKey]->set( $detail->sortColumn, $ordering[$entityKey] ) ? 1 : 0;
            }
        }

        return $numModified;

    }

    /**
     * Get entity PgLargeObject
     * @param PgLargeObject $value
     * @return mixed
     */
    public static function get_PgLargeObject( &$value, Base $entity )
    {
        if( $value instanceof PgLargeObject ){
            return $value->isDeleted()
                ? null
                : $value;
        }
        if( is_intish($value) ) {
            $value = new Oid( $value, $entity->r()->db );
        }
        return !is_null($value)
            ? new PgLargeObject( $value )
            : null;
    }

    /**
     * Set PgLargeObject on entity
     * @param PgLargeObject $value
     * @return PgLargeObject
     */
    public static function set_PgLargeObject( $value, $inputValidate, Base $entity )
    {
        if( $value instanceof PgLargeObject ){
            return $value;
        }
        if( is_intish($value) ) {
            $value = new Oid( $value, $entity->r()->db );
        }
        return is_null( $value )
            ? null
            : new PgLargeObject( $value );
    }

}