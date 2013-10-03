<?php

namespace Bond\Pg\Converter;

class PgNumber implements ConverterInterface
{
    public function fromPg($data, $type = null)
    {
        return null === $data ? null : $data + 0;
    }
}