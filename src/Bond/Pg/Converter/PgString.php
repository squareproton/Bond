<?php

namespace Bond\Pg\Converter;

class PgString implements ConverterInterface
{
    public function __invoke($data, $type = null)
    {
        return null === $data ? null : $data; // str_replace('\\"', '"', $data);
    }
}