<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task;

use Bond\Container;

use Bond\RecordManager\Task;
use Bond\RecordManager\Task\Normality;

abstract class NormalityCollection extends Normality
{

    /**
     * Check a collection only has one class. Determine this and return it.
     * @param array $collection
     * @return string
     */
    protected static function getClassFromCollection( array $collection )
    {
        // Make sure everything in this container is of __exactly__ the same type.
        // This is a stronger check than the entity container performs (child entities are ok in the container)
        $classes = array_unique( array_map( 'get_class', $collection ) );

        if( count( $classes ) > 1 ) {
            // TODO this could fallback to persisting every entity individually. That would work.
            throw new \Exception(
                sprintf(
                    "Can't manage entities in a container with multiple types, these are %s.",
                    implode( ", ", $classes )
                )
            );
        }
        return array_pop( $classes );

    }

    /**
     * Does this object present the required methods to be automatically saved by RecordManager
     *
     * Is the object a Container and is it Normality::isCompatible()?
     *
     * @param mixed Object
     * @return array|null (booly)
     */
    public static function isCompatible( $object, &$error = null )
    {

        if( !( $object instanceof Container ) ) {
            $error = 'Only Entity\\Containers are NormalityCollection compatible';
            return false;
        }

        // Pete. Humm. Not sure we even need this one. It probably is enough to be of the correct type.
        if( $firstElement = $object->peak() ) {
            return parent::isCompatible( $firstElement, $error );
        }

        return true;

    }

}