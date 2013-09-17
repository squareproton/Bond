<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Exception;

class EntityAlreadyRegisteredException extends \Exception
{
    public $entity;
    public $repository;
    public $entityManager;
    public function __construct( $entity, $repository, $entityManager )
    {
        $this->entity = $entity;
        $this->repository = $repository;
        $this->entityManager = $entityManager;

        $this->message(
            sprintf(
                "Entity %s has already been registered with the EntityManager.",
                $this->entity
            )
        );
    }
}