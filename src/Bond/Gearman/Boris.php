<?php

namespace Bond\Gearman;

use Bond\Gearman\Exception\JobUsesStdOutException;
use Bond\Gearman\Exception\WorkerExceedsMaxMemoryException;
use Bond\Gearman\Exception\WorkerExceedsMaxTimeException;
use Bond\Gearman\Exception\WorkerExceedsMaxConnectionsException;

use \GearmanJob;
use \GearmanWorker;
use \ReflectionClass;
use \ReflectionMethod;

use Bond\Profiler;
use Bond\Pg\Resource;

use Bond\MagicGetter;

class Boris extends GearmanWorker
{

    use MagicGetter;

    const JSON_ENCODED = 1;
    const JSON_NOT = 2;
    const JSON_DEFAULT = 2;

    const LOG_LIMITED = 4;
    const LOG_VERBOSE = 8;
    const LOG_DEFAULT = 0;

    private $memoryLimit;
    private $timeLimit;
    private $connectionLimit;

    private $startTime;
    private $jobCount = 0;

    private $jobNamePrefix = null;

    /**
     * Determines if this worker instance should continue to execute
     */
    private $execute = true;

    public function __construct( $memoryLimit = 0, $timeLimit = 0, $connectionLimit = 0 )
    {

        parent::__construct();

        $this->memoryLimit = max( (int) $memoryLimit, 0 );
        $this->timeLimit = max( (int) $timeLimit, 0 );
        $this->connectionLimit = max( (int) $connectionLimit, 0 );

        $this->startTime = microtime(true);

        d_pr(
            sprintf(
                "%s started. Pid %s",
                realpath($_SERVER['SCRIPT_NAME']),
                getmypid()
            )
        );

        // register a job outside of the normal process
        $pid = getmypid();

        $this->addFunction(
            "worker.{$pid}.status",
            function( GearmanJob $job ) {
                return json_encode( $this->status() );
            }
        );

        $this->addFunction(
            "worker.{$pid}.quit",
            function( GearmanJob $job ) {
                $this->execute = false;
                return json_encode( $this->status() );
            }
        );

    }

    /**
     * Set the job name prefix
     * @param string
     * @param \Bond\Gearman\Boris
     */
    public function setJobNamePrefix( $prefix )
    {
        $this->jobNamePrefix = (string) $prefix;
        return $this;
    }

    private function status()
    {
        return array(
            'memoryUsage' => memory_get_usage(),
            'memoryUsageOperatingSystem' => memory_get_usage(true),
            'startTime' => $this->startTime,
            'uptime' => microtime(true) - $this->startTime,
            'jobCount' => $this->jobCount,
            'dbConnections' => Resource::numInstances(),
            'timeLimit' => $this->timeLimit,
            'memoryLimit' => $this->memoryLimit,
            'connectionLimit' => $this->connectionLimit,
            'pid' => getmypid(),
            'script' => "{$_SERVER['PWD']}/{$_SERVER['SCRIPT_NAME']}",
        );
    }

    public function __destruct()
    {
        d_pr(
            sprintf(
                "%s/%s Pid %s. Memory %s. Limit %s. Time %s. Limit %s.",
                $_SERVER['PWD'],
                $_SERVER['SCRIPT_NAME'],
                getmypid(),
                size_human( memory_get_usage(true) ),
                size_human( $this->memoryLimit ),
                Profiler::formatTime( microtime(true) - $this->startTime ),
                Profiler::formatTime( $this->timeLimit )
            )
        );
    }

    public function launch()
    {

        // make a unlimited size buffer
        ob_start(null,0);

        // Blocking Work loop
        try {

            while ($this->execute) {

                $this->work();

                // Fix any database connections which have fallen by the wayside
                Resource::ensure();

                if ($this->returnCode() !== GEARMAN_SUCCESS) {
                    break;
                }

                // output buffering
                if( ob_get_length() ) {
                    $buffer = ob_get_contents();
                    ob_clean();
                    throw new JobUsesStdOutException( $buffer );
                }
                ob_flush();

                if( $this->memoryLimit and $this->memoryLimit < ( $memoryUsage = memory_get_usage(true) ) )  {
                    throw new WorkerExceedsMaxMemory( $this->memoryLimit, $memoryUsage );
                }

                if( $this->timeLimit and ( $this->timeLimit < ( $timeUsage = microtime(true) - $this->startTime ) ) ) {
                    throw new WorkerExceedsMaxTime( $this->timeLimit, $timeUsage );
                }

                if( $this->connectionLimit and $this->connectionLimit < ( $connectionUsage = Resource::numInstances() ) ) {
                    throw new WorkerExceedsMaxConnections( $this->connectionLimit, $connectionUsage );
                }

            }

        // handle output to console and gracefully exit. In production this should do something else.
        } catch ( JobUsesStdOut $e ) {

            // debugging
            echo $e->buffer;

        } catch ( WorkerExceedsMaxMemory $e ) {

            exit(50);

        } catch ( WorkerExceedsMaxTime $e ) {

            exit(50);

        }

    }

    /**
     * Register a class' static methods against a GearmanWorker.
     *
     * Eg, Given a class MailSender extends Worker {
     *      public static sendEmail(){...}
     *      public static mailMerge(){...}
     * }
     *
     * Calling MailSender->register( $worker );
     * Registers the functions "MailSender.sendEmail" and "MailSender.mailMerge" to the Gearman worker instance
     *
     * @param GearmanWorker Instance of worker the methods are to be instantited against
     * @param Integer. Bitmask or JSON_ENCODED, LOG_LIMITED, LOG_VERBOSE
     * @param String. Namespace for the worker functions.
     * @param String. Namespace separator for the the worker methods.
     */
    public function register( $worker, JobEvents $events, $options = 0, $namespace = null, $namespaceSeparator = '.' )
    {

        $reflectionClass = new ReflectionClass( $worker );

        $namespace = $namespace ?:
            preg_replace(
                '/^Gearman\./',
                '',
                str_replace('\\', '.', $reflectionClass->getName() )
            );

        // discover methods
        $workerMethods = array();
        foreach( $reflectionClass->getMethods() as $reflectionMethod ) {
            if( !$reflectionMethod->isStatic() and $reflectionMethod->isPublic() ) {
                $workerMethods[] = $reflectionMethod->name;
            }
        }

        // register methods
        $output = array();
        foreach( $workerMethods as $method ) {
            $jobName = "{$this->jobNamePrefix}{$namespace}{$namespaceSeparator}{$method}";
            $callback = array( $worker, $method );
            $output[] = $this->workerAddFunction(
                $jobName,
                $callback,
                $events,
                $this->getAnnotationOptions( $worker, $method )
            );
        }

        return array(
            'options' => $this->humanReadableOptions( $options ),
            'registered' => $output
        );
    }

    /**
     * Convert the human-unreadable callback into something human readable
     * @param Callable
     * @return string
     */
    private function humanReadableCallback( $callback )
    {
        return sprintf(
            '%s->%s()',
            get_class( $callback[0] ),
            $callback[1]
        );
    }

    /**
     * Convert the human-unreadable options array into a array of values
     * @param Integer bitmask
     * @return array() human readable options
     */
    private function humanReadableOptions( $options )
    {
        $reflectionClass = new ReflectionClass(__CLASS__);
        $output = array();
        foreach( $reflectionClass->getConstants() as $name => $value ) {
            if( is_int( $value ) and $options & $value and strpos( $name, 'DEFAULT' ) === false ) {
                $output[] = $name;
            }
        }
        return $output;
    }

    /**
     * Make an attachment to a worker
     *
     * @param GearmanWorker
     * @param integer bitfield - see class constants
     * @param string The name of this operation exposed to GearmanClients
     * @param mixed php callback
     *
     * @return string Some logging text
     */
    public function workerAddFunction(  $jobName, $callback, JobEvents $events, $options )
    {

        $humanReadableCallback = $this->humanReadableCallback( $callback );

        $this->addFunction(
            $jobName,
            function( GearmanJob $job ) use ( $callback, $humanReadableCallback, $events, $options, $jobName ) {
                $this->jobCount++;
                $job->name = $jobName;
                // loggin..
                if( $options & self::LOG_VERBOSE ) {
                    printf(
                        "    %s %s -> %s('%s')\n",
                        date('Y-m-d H:i:s'),
                        $humanReadableCallback,
                        $functionMethod,
                        $job->workload()
                    );
                } elseif( $options & self::LOG_LIMITED ) {
                    printf(
                        "    %s -> %s(...)\n",
                        $humanReadableCallback,
                        $functionMethod
                    );
                }

                // do the work and trap errors
                // maybe invoke the exception handler
                try {
                    // actually do the work and return
                    $args = array( $job );

                    if( $options & self::JSON_ENCODED ) {

                        $job->jsonEncoded = true;
                        $args[] = json_decode( $job->workload(), true );

                    } else if( ( $workload = $job->workload() ) && is_string( $workload ) ) {

                        $job->jsonEncoded = false;
                        $args[] = $workload;

                    }

                    $events->jobStart( $job );

                    $output = call_user_func_array( $callback, $args );

                    if( $options & self::JSON_ENCODED ) {
                        $output = json_encode( $output );
                    }

                    $events->jobEnd( $job, $output );
                } catch ( \Exception $e ) {
                    $output = null;
                    $events->jobEnd( $job, $output );
                    $events->exceptionHandler( $e, $job );
                }

                // return
                return $output;

            }
        );
        return sprintf(
            "%s with options [%s]",
            $humanReadableCallback,
            implode(', ', $this->humanReadableOptions( $options ) )
        );
    }

    /**
     * Get annotation from addendum
     * @
     */
    private function getAnnotationOptions( $class, $method )
    {

        $reflectionMethod = new \ReflectionMethod( $class, $method );
        $docblockComment = $reflectionMethod->getDocComment();

        $options = 0;

        // json
        if( stristr( $docblockComment, "@JSON_DEFAULT" ) !== false ) {
            $options += $options & ( self::JSON_ENCODED | self::JSON_NOT ) ? 0 : self::JSON_DEFAULT;
        }
        if( stristr( $docblockComment, "@JSON_NOT" ) !== false ) {
            $options = ( $options & ~self::JSON_ENCODED ) | self::JSON_NOT;
        }
        if( stristr( $docblockComment, "@JSON_ENCODED" ) !== false ) {
            $options = $options & ~self::JSON_NOT | self::JSON_ENCODED;
        }

        // logging
        if( stristr( $docblockComment, "@LOG_DEFAULT" ) !== false ) {
            $options += $options & ( self::LOG_VERBOSE + self::LOG_LIMITED ) ? 0 : self::LOG_DEFAULT;
        }
        if( stristr( $docblockComment, "@LOG_LIMITED" ) !== false ) {
            $options = $options & ~self::LOG_VERBOSE | self::LOG_LIMITED;
        }
        if( stristr( $docblockComment, "@LOG_VERBOSE" ) !== false ) {
           $options = $options & ~self::LOG_LIMITED | self::LOG_VERBOSE;
        }

        return $options;

    }

    /**
     * Log the output of register to the console
     *
     * @param array Output as returned but self::register
     * @return void
     */
    public function logRegistrationToConsole( $output )
    {
        // debuggin out
        printf(
            "Registering workers... with options %s\n    %s\n\n",
            implode( ", ", $output['options'] ) ,
            implode("\n    ", $output['registered'] )
        );
    }

    /**
     * Return the namespace used
     * @param string $class
     * @param string $namespace
     * @return string
     */
    private function getClassNamespace( $class, $namespace )
    {
        return $namespace ?: preg_replace('/^Gearman\./', '', str_replace('\\', '.', $class));
    }

    /**
     * Utility function to return a base classname sans any namespace
     * @param string
     * @return string
     */
    private function getUnqualifiedClass( $class )
    {
        if (false !== $nsSep = strrpos($class, '\\')) {
            return substr($class, $nsSep + 1);
        }
        return $class;
    }

}
