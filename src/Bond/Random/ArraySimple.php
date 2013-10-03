<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class ArraySimple implements RandomInterface
{
    private $options;
    private $callback = null;
    private $last;
    function __construct( array $options, $callback = null )
    {
        $this->options = $options;
        $this->callback = $callback;
    }
    function __invoke()
    {
        $value = $this->options[array_rand($this->options)];
        return $this->last  = (
            $this->callback ?
                call_user_func( $this->callback, $value ) :
                $value
        );
    }
    public function last()
    {
        return $this->last;
    }
}