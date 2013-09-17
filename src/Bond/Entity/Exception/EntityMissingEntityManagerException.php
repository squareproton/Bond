<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Exception;

class EntityMissingEntityManagerException extends \Exception
{
    public $entity;
    public function __construct( $entity )
    {
        $this->entity = $entity;
        $this->message = sprintf(
            "You are trying to access the EntityManager of a Entity `%s` which doesn't have this property set. This function is eventually going to be depreciated. Please talk to Pete.",
            get_class( $this->entity )
        );
    }
}