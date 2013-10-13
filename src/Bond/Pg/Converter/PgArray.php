<?php

namespace Bond\Pg\Converter;

class PgArray implements ConverterInterface
{
    private $baseConverter;
    public function __construct( ConverterInterface $baseConverter )
    {
        $this->baseConverter = $baseConverter;
    }
    public function fromPg($data, $type = null)
    {
        if( null === $data ) {
            return null;
        } elseif ( $data === '{}' or $data === '{NULL}' ) {
            return array();
        }
        $data = trim($data, "{}");
        $data = str_replace('\\\\', '\\', $data);
        $data = str_getcsv($data, ',', '"', '\\' );
        return array_map(
            [$this->baseConverter, 'fromPg'],
            $data
        );
    }
}