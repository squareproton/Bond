<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container\FindFilterComponent;

use Bond\Container\FindFilterComponent;

class PropertyAccessScalarEquality extends FindFilterComponent
{
    /**
     * {@inheritDoc}
     */
    public function check($obj)
    {
        return $this->propertyMapper->get($obj) === $this->value;
    }

}