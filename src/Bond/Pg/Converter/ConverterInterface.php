<?php

namespace Bond\Pg\Converter;

interface ConverterInterface
{
    public function __invoke($data);
}