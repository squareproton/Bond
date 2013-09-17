<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\FindFilterComponent;

use Bond\Container\FindFilterComponent;

class PropertyAccessContainerEquality extends FindFilterComponent
{

    /**
     * {@inheritDoc}
     */
    public function check($obj)
    {
        return $this->value->contains( $this->propertyMapper->get($obj) );
    }

    /**
     * Standard setter
     * @param callable
     * @return bool
     */
    public function getCannotMatch( callable $cannotMatch )
    {
        // if every item in the container cannotMatch, ...
        return $this->value->every($cannotMatch);
    }

}