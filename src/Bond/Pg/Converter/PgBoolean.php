<?php

namespace Bond\Pg\Converter;

class PgBoolean implements ConverterInterface
{
    public function fromPg($data, $type = null)
    {
        return null === $data ? null : ($data === 't');
    }
}