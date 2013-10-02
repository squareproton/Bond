<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class ArrayLoop implements RandomInterface
{
    private $options;
    function __construct( array $options )
    {
        if( !$options ) {
            throw new \Exception("Must pass at least one option.");
        }
        $this->options = $options;
        reset( $this->options );
    }
    function __invoke()
    {
        list($key,$value) = each( $this->options );
        if( $key !== null ) {
            return $value;
        }
        reset( $this->options );
        return $this();
    }
}