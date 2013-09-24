<?php

namespace Bond\Gearman\Exception;

use Bond\Profiler;

class WorkerExceedsMaxTimeException extends \Exception
{

    public $limit;
    public $usage;

    public function __construct( $limit, $usage )
    {
        $this->limit = $limit;
        $this->usage = $usage;
        $this->message = sprintf(
            "Script time limit set to %s used %s.",
            Profiler::formatTime( $limit ),
            Profiler::formatTime( $usage )
        );
    }

}