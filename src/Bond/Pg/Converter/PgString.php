<?php

namespace Bond\Pg\Converter;

class PgString implements ConverterInterface
{
    public function fromPg($data, $type = null)
    {
        return null === $data ? null : str_replace('\\"', '"', $data);
    }
}