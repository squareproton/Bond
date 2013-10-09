<?php

namespace Bond\Random;

use Bond\Random\RandomInterface;

class Callback implements RandomInterface
{
    public $callback;
    public $args;
    private $last;
    function __construct( $callback )
    {
        $args = func_get_args();
        $this->callback = array_shift( $args );
        $this->args = $args;
    }
    function __invoke()
    {
        return $this->last = call_user_func_array( $this->callback, $this->args );
    }
    public function last()
    {
        return $this->last;
    }
}
