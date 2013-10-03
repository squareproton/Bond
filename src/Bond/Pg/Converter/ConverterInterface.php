<?php

namespace Bond\Pg\ConverterInterface;

interface ConverterInterface
{
    public function fromPg($data, $type = null);
}