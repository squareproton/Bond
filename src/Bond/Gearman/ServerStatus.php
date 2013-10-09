<?php

namespace Bond\Gearman;

/**
 * Provides information about a running gearman server without resorting to a php extension
 * Thanks to http://stackoverflow.com/questions/2752431/any-way-to-access-gearman-administration
 */
class ServerStatus {

    /**
     * @var string
     */
    private $host = "127.0.0.1";

    /**
     * @var int
     */
    private $port = 4730;

    /**
     * @param string $host
     * @param int $port
     */
    public function __construct( $host = null, $port = null ){
        if( !is_null($host) ){
            $this->host = $host;
        }
        if( !is_null($port) ){
            $this->port = $port;
        }
    }

    /**
     * @param string $key
     */
    public function __get( $key )
    {
        if( isset( $this->$key ) ) {
            return $this->$key;
        }
        throw new \InvalidArgumentException("Don't know about key {$key}");
    }

    /**
     * @param string $errorMessage
     */
    public function isAlive( &$errorString = null )
    {
        $status = $this->getStatus( $errorNumber, $error );
        return $errorNumber === 0;
    }

    /**
     * @return array | null
     */
    public function getStatus(&$errorNumber = null, &$errorString = null){
        $status = null;
        $errorNumber = null;
        $errorString = null;
        $handle = @fsockopen($this->host,$this->port,$errorNumber,$errorString,30);
        if($handle!=null){
            fwrite($handle,"status\n");
            while (!feof($handle)) {
                $line = fgets($handle, 4096);
                if( $line==".\n"){
                    break;
                }
                if( preg_match("~^(.*)[ \t](\d+)[ \t](\d+)[ \t](\d+)~",$line,$matches) ){
                    $function = $matches[1];
                    $status['operations'][$function] = array(
                        'function' => $function,
                        'total' => (int) $matches[2],
                        'running' => (int) $matches[3],
                        'connectedWorkers' => (int) $matches[4],
                    );
                }
            }
            fwrite($handle,"workers\n");
            while (!feof($handle)) {
                $line = fgets($handle, 4096);
                if( $line==".\n"){
                    break;
                }
                // FD IP-ADDRESS CLIENT-ID : FUNCTION
                if( preg_match("~^(\d+)[ \t](.*?)[ \t](.*?) : ?(.*)~",$line,$matches) ){
                    $fd = $matches[1];
                    $status['connections'][$fd] = array(
                        'fd' => $fd,
                        'ip' => $matches[2],
                        'id' => $matches[3],
                        'function' => $matches[4],
                    );
                }
            }
            fclose($handle);
        }

        return $status;
    }

    private function extractWorkersFromStatus( $status )
    {
        $workers = [];
        foreach( $status['connections'] as $worker ) {
            if( preg_match( '/\\b(worker|node).(\d+).status\\b/', $worker['function'], $matches ) ) {
                $workers[] = array(
                    'ip' => $worker['ip'],
                    'pid' => $matches[2],
                    'functions' => explode( " ", $worker['function'] ),
                    'statusJob' => $matches[0],
                );
            }
        }
        return $workers;
    }

    public function getJobStatusForTablet()
    {

        $status = $this->getStatus();

        $workers = $this->extractWorkersFromStatus($status);

        $ops = [];
        foreach( $status['operations'] as $op ) {
            // legacy gearman job that doesn't have anything to do with anyone anymore
            if( $op['total'] == 0 and $op['running'] == 0 and $op['connectedWorkers'] == 0 ) {
                continue;
            }
            if( preg_match( '/^(worker|node)./', $op['function'] ) ) {
                continue;
            }

            $op['workers'] = [];
            foreach( $workers as $id => $wops ) {
                if( in_array( $op['function'], $wops['functions'] ) ) {
                    $op['workers'][] = $wops['pid'];
                }
            }
            $op['workers'] = implode( ', ', $op['workers'] );

            $ops[] = $op;
        }

        return $ops;
    }

    public function getWorkerStatusForTablet()
    {
        $workers = $this->extractWorkersFromStatus( $this->getStatus() );

        $client = new \GearmanClient();
        $client->addServer();
        $client->setTimeout(2);

        $jobs = [];
        $client->setCompleteCallback(
            function ($task) use (&$jobs) {
                $data = $task->data();
                $jobs[] = json_decode( $data, true );
            }
        );

        foreach( $workers as $worker ) {
            $client->addTaskHigh(
                $worker['statusJob'],
                "[]"
            );
        }

        if( !($response = $client->runTasks()) ) {
        }

        return $jobs;

    }

}