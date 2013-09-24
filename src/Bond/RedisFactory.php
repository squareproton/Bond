<?php

namespace Bond;

// Nothing much here. Simple multiton wrapper
// Potential confusion because the phpredis class is also 
class RedisFactory {

    const DEFAULT_HOST = '127.0.0.1';
    const DEFAULT_PORT = 6379;

    /**
     * @var array multiton store
     */
    private static $instances = array();

    /**
     * Standard multiton factory.
     * @param $connection
     * @param $cache
     * @return \Redis
     */
    public static function factory( $connection = 'DEFAULT', $cache = true )
    {
        if( !isset( self::$instances[$connection] ) ) {
            $redis = new \Redis();
            $redis->connect( self::DEFAULT_HOST, self::DEFAULT_PORT );
            if( $cache ) {
                self::$instances[$connection] = $redis;
            }
            return $redis;
        }
        return self::$instances[$connection];
    }

}