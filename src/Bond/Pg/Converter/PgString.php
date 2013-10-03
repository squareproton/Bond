<?php

namespace Bond\Pg\Converter;

class PgString implements ConverterInterface
{
    public function fromPg($data, $type = null)
    {
        return null === $data ? str_replace('\\"', '"', $data);
    }
}