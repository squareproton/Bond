<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity;

class Sort
{

    /**
     * Generate a sort by property callback
     * @param string $property property to sort by
     */
    public static function generateByPropertyClosure( $property, $direction = SORT_ASC )
    {

        if( $direction === SORT_DESC ) {
            $aLTb = 1;
            $aGTb = -1;
        } else {
            $aLTb = -1;
            $aGTb = 1;
        }

        return function ( \Bond\Entity\Base $a, \Bond\Entity\Base $b ) use ( $property, $aLTb, $aGTb ) {
            $propertyA = $a->get($property);
            $propertyB = $b->get($property);
            if( $propertyA === $propertyB ) {
                return 0;
            }
            return ( $propertyA < $propertyB ) ? $aLTb : $aGTb;
        };

    }

}