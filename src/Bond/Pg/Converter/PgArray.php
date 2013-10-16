<?php

namespace Bond\Pg\Converter;

class PgArray extends CompositeParser implements ConverterInterface
{

    const ESCAPE_CHARS = '\\';
    const CHARS_NEEDING_ESCAPE = '\\"';
    const FIELD_ENCLOSED_BY = '"';
    const FIELD_SEPARATOR = ',';
    const TRIM_LEADING = '{}';

    private $baseConverter;

    public function __construct( ConverterInterface $baseConverter )
    {
        $this->baseConverter = $baseConverter;

        // $allowedBaseConverters = [PgString::class, PgNumber::class];
        // if( !in_array( get_class($this->baseConverter), $allowedBaseConverters ) ) {
        //     print_r( $this->baseConverter );
        //     die();
        // }
    }

    public function __invoke($data)
    {
        if( null === $data ) {
            return null;
        }
        return array_map(
            $this->baseConverter,
            $this->parseComposite($data)
        );
    }

}