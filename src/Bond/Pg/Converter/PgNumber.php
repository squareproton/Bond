<?php

namespace Bond\Pg\Converter;

class PgNumber implements ConverterInterface
{
    public function __invoke($data, $type = null)
    {
        return null === $data ? null : $data + 0;
    }
}