<?php

namespace Bond\Gearman\Exception;

class JobUsesStdOutException extends \Exception
{

    public $buffer;

    public function __construct( $buffer )
    {
        $this->buffer = $buffer;
        $this->message = sprintf(
            "Job wrote %d bytes to console.",
            strlen( $buffer )
        );
    }

}