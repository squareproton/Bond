<?php

namespace Bond\Gearman;

use Bond\MagicGetter;
use Bond\MagicSetter;

use Bond\ExceptionHandler;
use Bond\ServerSettings;
use Bond\BackTrace;

use Bond\Gearman\SymfonyKernel;

use GearmanJob;
use Exception;

class JobEvents
{

    use MagicGetter;

    public $exceptionHandler;
    public $jobStart;
    public $jobEnd;

    public function __construct( Callable $exceptionHandler, Callable $jobStart, Callable $jobEnd )
    {
        $this->exceptionHandler = $exceptionHandler;
        $this->jobStart = $jobStart;
        $this->jobEnd = $jobEnd;
    }

    public function __call( $name, $arguments )
    {
        if( isset( $this->$name ) ) {
            return call_user_func_array( $this->$name, $arguments );
        }
        throw new \InvalidArgumentException("Fuck that.");
    }

    public static function makeDefault()
    {

        // gearman exception handler
        $exceptionHandler = function ( Exception $e, GearmanJob $job ) {

            $trace = new BackTrace( $e );

            // log exception to database
            $data = array(
                'fn' => $job->functionName(),
                'data' => $job->workload(),
                'message' => $e->getMessage(),
                'trace' => $trace->getSane(),
                'createTimestamp' => date('Y-m-d H:i:s'),
            );

            foreach( $data['trace'] as &$trace ) {
                unset( $trace['args'] );
            }

            $data['trace'] = serialize($data['trace']);

            fputcsv( STDERR, $data, ',', "'" );

            d_e( $e );

            $recipients = ServerSettings::init()->exceptionEmailAddressesGet();

            $handler = new ExceptionHandler(
                $e,
                "gearman exception",
                array(),
                SymfonyKernel::getService('mailer'),
                'support@printnode.com',
                $recipients
            );

            $handler->send();

        };

        // job start logging
        $jobStart = function( GearmanJob $job ) {

            $job->startTime = microtime(true);

        };

        // job end logging
        $scriptStartTime = microtime(true);

        $jobEnd = function( GearmanJob $job, $output ) use ( $scriptStartTime ) {

            $scriptDuration = microtime(true) - $scriptStartTime;
            $duration = (microtime(true) - $job->startTime);
            $memoryUsage = memory_get_usage(true);

            $data = array(
                'memoryUsage' => $memoryUsage,
                'memoryUsageHuman' => size_human( $memoryUsage ),
                'fn' => $job->functionName(),
                'data' => $job->workload(),
                'duration' => $duration,
                'scriptDuration' => $scriptDuration,
                'createTimestamp' => date('Y-m-d H:i:s'),
            );

            fputcsv( STDOUT, $data, ',', "'" );

        };

        return new self( $exceptionHandler, $jobStart, $jobEnd );

    }

}