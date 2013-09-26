<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Database\DatabaseInterface;
use Bond\Database\ResourceInterface;

use Bond\Exception\BadTypeException;
use Bond\Exception\DepreciatedException;

use Bond\Pg\Exception\MissingParameterStateException;
use Bond\Pg\QueryExceptionFactory;
use Bond\Pg\Resource;
use Bond\Pg\Result;

use Bond\Sql\SqlInterface;
use Bond\Sql\Raw;

use Bond\MagicGetter;

/* Manager of the pg_connect resource
 *
 * Query's will be passed to this class
 */
class Pg implements DatabaseInterface, \Serializable
{

    use MagicGetter;

    /**
     * CLASS CONSTANTS
     */
    const SESSION = 'SESSION';
    const LOCAL = 'LOCAL';

    const RECONNECT_AS_REQUIRED = 1024;
    const RECONNECT_NO = 2048;
    const RECONNECT_DEFAULT = 2048;

    const PREVIOUS = '__PREVIOUS';
    const ORIGINAL = '__ORIGINAL';

    const QUERY_PARSED = 'query_parsed';
    const QUERY_OK = 'query_ok';

    /**
     * the PgResource object that was used to instaniate this object
     * @var Resource
     */
    private $resource;

    /**
     * A identifier to help debugging
     * @var string
     */
    private $name;

    /**
     * The number of queries that have been executed against this connection
     * Really useful for unit testing
     */
    private $numQuerys = 0;

    /**
     * Debugging object
     */
    private $debug;

    /**
     * Parameter store
     * Array of parameter values to store against a named restore point
     */
    public $parameterStore = array();

    /**
     * Manage a database connection
     *
     * @param PgResource $resource
     */
    public function __construct( $resource, $name = null )
    {
        $this->resource = $resource;
        $this->name = (string) $name;
        $this->debug = Debug::get(__CLASS__);
    }

    public function serialize()
    {
        throw new DepreciatedException();
    }

    public function unserialize($data)
    {
        throw new DepreciatedException();
    }

    /**
     * Execute a query against the database.
     *
     * @param SqlInterface $query
     * @param bitfield Options bitfield
     * @return \Bond\Pg\Result
     */
    public function query( SqlInterface $query, $options = 0 )
    {

        $options += $options & ( self::RECONNECT_AS_REQUIRED | self::RECONNECT_NO ) ? 0 : self::RECONNECT_DEFAULT;

        $this->numQuerys++;

        $timings = array();
        $timings['start'] = microtime( true );

        $sql = $query->parse( $this );
        $timings['query_parsed'] = microtime( true );

        $this->debug->emit( Debug::INFO, self::QUERY_PARSED, $sql );

        // debugging search path pest
        // $search_path = pg_query( $this->resource->get(), "SHOW search_path"); $result = pg_fetch_row( $search_path ); d_pr( $result );

        // set exception
        $resource = $this->resource->get( $options & self::RECONNECT_AS_REQUIRED );

        try {

            // really ghetto debuggin for when shit really hits the fan
            // file_put_contents( '~/sqllog.out', "{$sql}\n\n", FILE_APPEND );

            // execute query
            if( false === $result = @pg_query( $resource, $sql ) ) {
                throw new \Exception();
            }

        } catch( \Exception $e ) {

            $exceptionFactory = new QueryExceptionFactory( $resource, $sql, $this->debug );
            throw $exceptionFactory->get();

        }

        $timings['query_executed'] = microtime( true );
        $timings['total'] = $timings['query_executed'] - $timings['start'];
        $timings['parsing'] = $timings['query_parsed'] - $timings['start'];
        $timings['total'] = $timings['query_executed'] - $timings['query_parsed'];

        $this->debug->emit( self::QUERY_OK, $sql, $timings );

        $resultResourceObject = new Result( $result, $this, $query, $timings );

        return $resultResourceObject;

    }

    /**
     * Get postgres notifications
     * @return array
     */
    public function getNotification()
    {
        return pg_get_notify( $this->resource->get() );
    }

    /**
     * Get postgres last notice()
     * @return string
     */
    public function getLastNotice()
    {
        $notice = pg_last_notice( $this->resource->get() );
        // this generic text doesn't actually tell us anything and clutters up the notice.
        // remove
        $notice = str_replace( 'LOCATION:  exec_stmt_raise, pl_exec.c:2840', '', $notice );
        return trim( $notice );
    }

    /**
     * Get all notifications
     * @param bool JSON decode payload
     * @return array
     */
    public function getNotifications( $jsonDecodePayload = false )
    {
        $output = array();
        while( $notification = $this->getNotification() ) {
            if( $jsonDecodePayload ) {
                $notification['payload'] = json_decode($notification['payload'], true);
            }
            $output[] = $notification;
        }
        return $output;
    }

    /**
     * Subscribe to a postgres channel
     */
    public function listen( $channels )
    {
        $channels = is_array( $channels ) ? $channels : array( $channels );
        foreach( $channels as $channel ) {
            // clean up channel - it doesn't require quoting (not sure why)
            // make safe with whitelist of chars
            $channel = preg_replace( "/[^a-zA-Z\._:]/", '', $channel );
            $this->query( new Raw( "LISTEN {$channel}" ) );
        }
    }

    /**
     * Return a value made safe for insertion into a database
     *
     * @param array $modifiers Array of \Closures to be applied before returning
     * @return string
     */
    public function quote( $value, array $modifiers = [] )
    {
        if( is_null( $value ) ) {
            $value = 'NULL';
        } elseif( is_bool( $value ) ) {
            $value = $value ? 'TRUE' : 'FALSE';
        } elseif( is_int( $value ) ) {
            $value = (string) $value;
        } elseif( $value instanceof SqlInterface ) {
            $value = $value->parse( $this );
        } else {
            $value = "'" . pg_escape_string( $this->resource->get(), $value ) . "'";
        }

        foreach( $modifiers as $modifier ) {
            $modifier( $value );
        }

        return $value;
    }

    /**
     * Quote identifier. Compatible with postgres 9.1
     * http://www.postgresql.org/docs/9.1/static/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS
     * @param string Identifier
     * @return string Quoted Identifier
     */
    public function quoteIdent( $identifier )
    {

        // a identifier can be composite - separated by dots
        $fragments = explode('.',$identifier);
        foreach( $fragments as &$fragment ) {
            $fragment = sprintf(
                '"%s"',
                str_replace( '"', '""', $fragment )
            );
        }

        return implode('.',$fragments);

    }

    public function quoteBytea( $value )
    {
        return "'". pg_escape_bytea( $this->resource->get(), $value ). "'";
    }

    /**
     * Get a postgres runtime parameter
     * @return string
     */
    public function getParameter( $parameter )
    {

        self::isParameterAllowed( $parameter );

        $value = $this->query(new Raw("SHOW {$parameter}"))->fetch( Result::FETCH_SINGLE );

        return ( $value === 'true' or $value === 'false' )
            ? \Bond\boolval( $value )
            : $value;

    }

    /**
     * Set a postgres runtime parameter
     * @param string
     * @param mixed
     * @param scalar
     * @param string self::SESSION | self::LOCAL
     * @return $this
     */
    public function setParameter( $parameter, $value, $restorePoint = self::PREVIOUS, $type = self::SESSION )
    {

        if (!is_scalar($value)) {
            throw new BadTypeException($value, 'scalar');
        }

        self::isParameterAllowed( $parameter );
        if( !in_array( $type, array( self::SESSION, self::LOCAL ) ) ) {
            throw new \InvalidArgumentException("WTF!");
        }

        if( $restorePoint and !is_scalar($restorePoint) ) {
            throw new \InvalidArgumentException( "RestorePoint has to be a scalar.");
        }

        // manage previous and original stores
        $currentValue = $this->getParameter( $parameter );

        if( !isset( $this->parameterStore[$parameter][self::ORIGINAL] ) ) {
            $this->parameterStore[$parameter][self::ORIGINAL] = $currentValue;
        }

        $this->parameterStore[$parameter][self::PREVIOUS] = $currentValue;

        // we can't quote a parameter value so regex
        /*
        if( preg_match_all('/[^a-zA-Z0-9_]/', $value, $matches ) ) {
            $badChars = implode('', $matches[0]);
            throw new \Exception( "We can't quote parameter values. Bad characters `{$badChars}`" );
        }
         */

        // bool
        if( $value === true ) {
            $value = 'true';
        } elseif( $value === false ) {
            $value = 'false';
        }

        // parameters
        if( $restorePoint !== self::PREVIOUS ) {
            $this->parameterStore[$parameter][$restorePoint] = $value;
        }

        $value = $this->query( new Raw("SET {$parameter} TO {$value}") );

        return $this;

    }

    /**
     * Restore a paramter to the value it had at a [restore]point
     * @param string
     * @param mixed
     * @return $this
     */
    public function restoreParameter( $parameter, $restorePoint = self::PREVIOUS )
    {
        if( !isset( $this->parameterStore[$parameter][$restorePoint] ) ) {
            throw new MissingParameterStateException( "parameter `{$parameter}` doesn't have a value stored at `{$restorePoint}`" );
        }
        return $this->setParameter( $parameter, $this->parameterStore[$parameter][$restorePoint], $restorePoint );
    }

    /**
     * We can't quote parameter's and therefore need to be really, really carefull what we pass through to SET and SHOW
     * @param string
     * @return bool
     */
    public static function isParameterAllowed( $parameter )
    {
        if(
            !preg_match( '/^bond\\.[a-z_]+$/', $parameter ) and
            !in_array( $parameter, array('search_path', 'timezone', 'intervalstyle') )
        ) {
            throw new \InvalidArgumentException("database getRuntimeParameter( `{$parameter}` ) isn't on the whitelist");
        }
        return true;
    }

    /**
     * Get the 'numerical' version of postgres as stripped from SELECT VERSION();
     * @return string
     */
    public function version()
    {
        return $this->query(
            new Raw(
                // urgh... if anyone knows a better way
                "SELECT v FROM ( SELECT unnest( regexp_matches( version(), 'PostgreSQL\W+([^ ]+)\W', 'g') ) v ) _;"
            )
        )->fetch( Result::FETCH_SINGLE );
    }

}