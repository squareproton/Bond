<?php

namespace Bond\Gearman\Exception;

use \GearmanJob;

class JobDataMalformedException extends \Exception
{
    public $job;
    public $error;
    public function __construct( GearmanJob $job, $error)
    {
        $this->job = $job;
        $this->error = $error;
        parent::__construct(
            sprintf(
                "GearmanJob %s. Payload malformed. %s. %s",
                $job->name,
                $error,
                $job->jsonEncoded ? json_decode( $job->workload(), true ) : $job->workload()
            )
        );
    }
}