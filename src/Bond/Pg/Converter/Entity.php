<?php

namespace Bond\Pg\Converter;

use Bond\Repository;

class Entity implements ConverterInterface, FlattenInterface
{

    private $repository;
    private $converter;

    public function __construct( Repository $repository, ConverterInterface $converter )
    {
        $this->repository = $repository;
        $this->converter = $converter;
    }

    public function __invoke($data)
    {
        if( null === $data = $this->converter->__invoke($data) ) {
            return null;
        }
        $entity = $this->repository->initByData($data);
        return $entity;
    }

    public function flatten($rows)
    {
        $container = $this->repository->makeNewContainer();
        return $container->add($rows);
    }

}