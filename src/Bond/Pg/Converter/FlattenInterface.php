<?php

namespace Bond\Pg\Converter;

interface FlattenInterface
{
    public function __invoke($rows);
}