<?php

namespace Bond\Gearman\Exception;

class WorkerExceedsMaxMemoryException extends \Exception
{

    public $limit;
    public $usage;

    public function __construct( $limit, $usage )
    {
        $this->limit = $limit;
        $this->usage = $usage;
        $this->message = sprintf(
            "Script memory limit set to %s used %s.",
            size_human( $limit ),
            size_human( $usage )
        );
    }

}