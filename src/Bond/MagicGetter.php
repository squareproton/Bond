<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Exception\UnknownPropertyForMagicGetterException;

/**
 * Provide a common completely bog standard getter
 */
trait MagicGetter
{

    /**
     * Standard magic getter
     */
    public function __get( $key )
    {
        if( property_exists( $this, $key ) ) {
            return $this->$key;
        }
        throw new UnknownPropertyForMagicGetterException( $this, $key );
    }

}