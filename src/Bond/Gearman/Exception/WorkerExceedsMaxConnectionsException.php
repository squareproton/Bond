<?php

namespace Bond\Gearman\Exception;

class WorkerExceedsMaxConnectionsException extends \Exception
{

    public $limit;
    public $usage;

    public function __construct( $limit, $usage )
    {
        $this->limit = $limit;
        $this->usage = $usage;
        $this->message = sprintf(
            "Max connections set to %s used %s.",
            $limit,
            $usage
        );
    }

}