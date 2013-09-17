<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Repository\Exception;

class EntityNotCompatibleWithRepositoryException extends \Exception
{
    public $entity;
    public $respository;
    public function __construct( $entity, $repository )
    {
        $this->entity = $entity;
        $this->repository = $repository;
        $this->message = sprintf(
            "Repository `%s` not compatible with entity `%s`",
            $this->repository->entityClass,
            get_class( $entity )
        );
    }
}