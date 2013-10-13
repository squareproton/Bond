<?php

namespace Bond\Pg\Converter;

class PgBitString implements ConverterInterface
{
    public function fromPg($data, $type = null)
    {
        return null === $data ? null : bindec($data);
    }
}