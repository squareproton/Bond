<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Exception\NeedsOverloadingException;
use Bond\Exception\SerializationBadTypeException;

/**
 * Provide a common serialize and unserialize functionality across classes.
 * Removes quite a lot of repeated boilerplate
 */
trait Serialize
{

    /**
     * Return a array which can be then serialized with either php's serialize or json_encode.
     * This function does not return a string because it's output may be part of a larger serialization chain
     * @return mixed
     */
    public function serialize()
    {
        return array(
            get_called_class(),
            $this->toArray()
        );
    }

    /**
     * To array. Return a array suitable to be passed to serialize
     * @return array
     */
    protected function toArray()
    {
        throw new NeedsOverloading( $this, __FUNCTION__ );
    }

    /**
     * From array. Reverse of toArray().
     * @return array
     */
    protected function fromArray( array $array )
    {
        throw new NeedsOverloading( $this, __FUNCTION__ );
    }

}