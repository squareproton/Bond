<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\FindFilterComponent;

use Bond\Container\FindFilterComponent;

class PropertyAccessArrayEquality extends FindFilterComponent
{
    /**
     * {@inheritDoc}
     */
    public function check($obj)
    {
        return in_array( $this->propertyMapper->get($obj), $this->value, true );
    }

    /**
     * Standard setter
     * @param callable
     * @return bool
     */
    public function getCannotMatch( callable $cannotMatch )
    {
        // iterate over each of our array values and see if anyoff them match
        foreach( $this->value as $_value ) {
            if( !$call_user_func( $cannotMatch, $_value ) ) {
                $this->cannotMatch = false;
                return;
            }
        }
        return true;
    }

}