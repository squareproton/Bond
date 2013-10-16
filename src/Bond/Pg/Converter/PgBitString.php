<?php

namespace Bond\Pg\Converter;

class PgBitString implements ConverterInterface
{
    public function __invoke($data, $type = null)
    {
        return null === $data ? null : bindec($data);
    }
}