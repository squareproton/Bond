<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Exception;

class EntityNotRegisteredException extends \Exception
{
    public $entity;
    public $entityManager;
    public function __construct( $entity, $entityManager )
    {
        $this->entity = $entity;
        $this->entityManager = $entityManager;

        $this->message = sprintf(
            "Entity %s isn't registered with the EntityManager.",
            $this->entity
        );
    }
}