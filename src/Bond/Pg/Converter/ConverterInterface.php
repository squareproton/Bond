<?php

namespace Bond\Pg\Converter;

interface ConverterInterface
{
    public function fromPg($data);
}