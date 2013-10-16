<?php

namespace Bond\Pg\Converter;

class PgRecord extends CompositeParser implements ConverterInterface
{

    const ESCAPE_CHARS = '\\"';
    const CHARS_NEEDING_ESCAPE = '\"';
    const FIELD_ENCLOSED_BY = '"';
    const FIELD_SEPARATOR = ',';
    const TRIM_LEADING = '()';

    public function __invoke($data)
    {
        if( null === $data ) {
            return null;
        }
        return $this->parseComposite($data);
    }

}