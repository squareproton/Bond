<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg;

use Bond\Exception\BadTypeException;

use Bond\Pg\Converter\FlattenInterface;
use Bond\Pg\Exception\NoConverterFound;

use Bond\Database\DatabaseInterface;
use Bond\Database\Exception\MoreThanOneRowReturnedException;
use Bond\Database\Exception\ResourcesAreReadonlyException;
use Bond\Database\Exception\UnknownPropertyException;
use Bond\Database\ResultInterface;

use Bond\Sql\SqlInterface;

use Bond\MagicGetter;

/**
 * Wrapper for a pg_result_resource with common helper methods.
 */
class Result implements ResultInterface, \Iterator, \ArrayAccess, \Countable
{

    use MagicGetter;

    /**
     * Class constants
     */
    const TYPE_DETECT = 1024;
    const TYPE_AGNOSTIC = 512;
    const TYPE_DEFAULT = 512;

    const FLATTEN_IF_POSSIBLE = 2;
    const FLATTEN_PREVENT = 4;
    const FLATTEN_DEFAULT = 2;

    const STYLE_ASSOC = 128;
    const STYLE_NUM = 256;
    const STYLE_DEFAULT = 128;

    const FETCH_SINGLE = 8;
    const FETCH_MULTIPLE = 16;
    const FETCH_DEFAULT = 16;

    const CACHE = 32;
    const CACHE_NOT = 64;
    const CACHE_DEFAULT = 32;

    /**
     * The SqlInterface that was executed against the database to generate this resource
     * @var SqlInterface
     */
    private $query;

    /**
     * Result fetching options
     * @var int
     */
    private $fetchOptions;

    /**
     * Fetch type. Postgres constant PGSQL_ASSOC || PGSQL_NUM
     * @var int
     */
    private $fetchType;

    /**
     * Fetch callback
     * @var calback
     */
    private $fetchCallback;

    /**
     * Fetch rownumber. The row number of the current position of fetchRow()
     * @var number of the current
     */
    private $fetchRowNumber;

    /**
     * Query timing information in seconds, more keys to follow.
     * @var array array( 'total' = '0.4' );
     */
    private $timings;

    /**
     * The all important postgres query result resource
     * @var pg_resource
     */
    private $resource;

    /**
     * The DatabaseInterface that generated this result.
     * @var Bond\Database\DatabaseInterface
     */
    private $db;

    /**
     * The number of fields in this result - cached
     * @var int
     */
    private $numFields = null;

    /**
     * The number of rows in the result - cached
     * @var int
     */
    private $numRows = null;

    /**
     * The number of affected rows - cached
     * @var int
     */
    private $affectedRows = null;

    /**
     * The cached output of fetch key'd by the arguments passed to fetch
     * @var array
     */
    private $cacheFetch = array();

    /**
     * Single column callback
     */
    private $flattenCallback;

    /**
     * Handle the output of a pg_query
     * @param resource pg_result resource
     * @param Bond\Pg\DatabaseInterface
     * @param Bond\Sql\SqlInterface
     * @param array Timings array
     * @param array $timings
     */
    public function __construct( $resource, DatabaseInterface $db, SqlInterface $query, array $timings = array() )
    {
        // type checking
        if( !is_resource( $resource ) or !get_resource_type( $resource ) == 'pgsql result' ) {
            throw new BadTypeException( $resource, "Postgres result resource" );
        }
        $this->resource = $resource;
        $this->db = $db;
        $this->query = $query;
        $this->timings = $timings;

        $this->setFetchOptions(0);
        $this->fetchRowNumber = 0;
    }

    /**
     * Set the result fetchOptions
     * @param int Bitmask
     * @return Bond\Pg\Resource
     */
    public function setFetchOptions( $fetchOptions )
    {

        // set fetchOptions
        if( $fetchOptions === null ) {
            return $this->fetchOptions;
        }

        if( !is_integer($fetchOptions) ) {
            throw new BadTypeException( $fetchOptions, 'int' );
        }

        $fetchOptions += $fetchOptions & ( self::TYPE_DETECT | self::TYPE_AGNOSTIC ) ? 0 : self::TYPE_DEFAULT;
        $fetchOptions += $fetchOptions & ( self::FLATTEN_IF_POSSIBLE | self::FLATTEN_PREVENT ) ? 0 : self::FLATTEN_DEFAULT;
        $fetchOptions += $fetchOptions & ( self::STYLE_ASSOC | self::STYLE_NUM ) ? 0 : self::STYLE_DEFAULT;
        $fetchOptions += $fetchOptions & ( self::FETCH_SINGLE | self::FETCH_MULTIPLE ) ? 0 : self::FETCH_DEFAULT;
        $fetchOptions += $fetchOptions & ( self::CACHE | self::CACHE_NOT ) ? 0 : self::CACHE_DEFAULT;

        // flatten
        $flatten = (bool) ( $fetchOptions & self::FLATTEN_IF_POSSIBLE ) && ( $this->numFields() === 1 );

        $this->buildFetchCallback( $fetchOptions, $flatten );

        $this->fetchOptions = $fetchOptions;

        return $this;
    }

    /**
     * Build a row processing callback based on the TYPE_DETECT and row flattening
     * @param bool $flatten
     * @return Callable
     */
    private function buildFetchCallback( $fetchOptions, $flatten )
    {

        $original = $this->fetchOptions & ( self::TYPE_DETECT | self::TYPE_AGNOSTIC | self::FLATTEN_IF_POSSIBLE | self::FLATTEN_PREVENT | self::STYLE_ASSOC | self::STYLE_NUM );
        $new = $fetchOptions & ( self::TYPE_DETECT | self::TYPE_AGNOSTIC | self::FLATTEN_IF_POSSIBLE | self::FLATTEN_PREVENT | self::STYLE_ASSOC | self::STYLE_NUM );

        // nothing has changed - don't need to change the callback
        if( $original === $new ) {
            return false;
        }

        // only fetch assoc if we're configured for it and we aren't flattening (which would destroy it anyway)
        $this->fetchType = ( $fetchOptions & self::STYLE_ASSOC and !$flatten ) ? PGSQL_ASSOC : PGSQL_NUM;

        // types and keys
        // determine types - this could be enabled to automatically convert types (to something other than a string)
        // off by default as the performance hit has yet to be determined
        $this->flattenCallback = null;
        if( $fetchOptions & self::TYPE_DETECT ) {

            $typeCallbacks = $this->getFieldTypesCallbacks();
            if( $this->fetchType === PGSQL_ASSOC ) {
                $keys = array_keys( $typeCallbacks );
            } else {
                $keys = range(0, count($typeCallbacks) - 1);
            }

            // flatten
            if( $flatten ) {

                $typeCallback = array_pop( $typeCallbacks );
                $this->fetchCallback = function( $row ) use ( $keys, $typeCallback ) {
                    return call_user_func( $typeCallback, $row[0] );
                };

                // single column interface
                if( $typeCallback instanceof FlattenInterface ) {
                    $this->flattenCallback = [$typeCallback, 'flatten'];
                }

            } else {

                $this->fetchCallback = function( $row ) use ( $keys, $typeCallbacks ) {
                    return array_combine(
                        $keys,
                        array_map( 'call_user_func', $typeCallbacks, $row )
                    );
                };

            }

        } elseif( $flatten ) {

            $this->fetchCallback = function( $row ) {
                return array_shift( $row );
            };

        } else {

            $this->fetchCallback = function( $row ) {
                return $row;
            };

        }

        return true;

    }

    /**
     * Return the bitmask
     * @return
     */
    public function getFetchOptions( &$humanReadable = null )
    {
        // aid to result fetching debugging
        // get fetch options in a human readable format
        if( func_num_args() ) {
            $humanReadable = [];
            $refl = new \ReflectionClass( __CLASS__ );
            foreach( $refl->getConstants() as $name => $value ) {
                if( $value & $this->fetchOptions and strpos( $name, 'DEFAULT' ) === false ) {
                    $humanReadable[] = $name;
                }
            }
        }
        return $this->fetchOptions;
    }

    /**
     * Here be the magic!
     * @param int Bitmask of the options
     * @param mixed The column name we want our output to be keyed by
     * @return mixed
     */
    public function fetch( $options = null, $keyResultsByColumn = null )
    {

        $this->setFetchOptions( $options );

        // caching
        if( $this->fetchOptions & self::CACHE ) {
            // get a cache key for the passed options (we only care about those options that will modify the returned result)
            $cacheKey = $this->fetchOptions & (
                self::TYPE_DETECT | self::TYPE_AGNOSTIC |
                self::FLATTEN_IF_POSSIBLE | self::FLATTEN_PREVENT |
                self::STYLE_ASSOC | self::STYLE_NUM |
                self::FETCH_SINGLE | self::FETCH_MULTIPLE
            );
            if( isset( $this->cacheFetch[$cacheKey] ) ) {
                return $this->cacheFetch[$cacheKey];
            }
        }

        // build output
        $output = array();

        // build output array
        // slight repetition of code here to help us avoid the isset( $types ) check for every row (there might be a __lot__ so this adds up).
        pg_result_seek( $this->resource, 0 );
        if( null === $keyResultsByColumn ) {
            while( $row = pg_fetch_array( $this->resource, NULL, $this->fetchType ) ) {
                $output[] = call_user_func( $this->fetchCallback, $row );
            }
        } else {
            while( $row = pg_fetch_array( $this->resource, NULL, $this->fetchType ) ) {
                $row = call_user_func( $this->fetchCallback, $row );
                $output[$row[$keyResultsByColumn]] = $row;
            }
        }

        // populate the numRows cache as we've now got this information as standard
        if( !isset( $this->numRows ) ) {
            $this->numRows = count( $output );
        }
        $this->fetchRowNumber = $this->numRows;

        // single result behaviour
        if( $this->fetchOptions & self::FETCH_SINGLE ) {

            if( $this->numRows() == 0 and $this->numFields() > 1 ) {

                $output = array();

            } elseif( $this->numRows() <= 1 ) {

                $output = array_pop( $output );

            // perhaps we should be returning a exception you call $singleResult and there is > 1 rows returned from the db
            } else {
                throw new MoreThanOneRowReturnedException( "{$this->numRows()} returned, FETCH_SINGLE doesn't apply here." );
            }

        }

        // do we have any post processing flattening callback to execute
        // Eg, entities going into container
        if( $this->flattenCallback ) {
            $output = call_user_func($this->flattenCallback, $output);
        }

        // cache population
        if( isset($cacheKey) ) {
            $this->cacheFetch[$cacheKey] = $output;
        }

        return $output;

    }

    /**
     * Return a array of php callbacks to be applied to a result set
     * @param array $types
     */
    private function getFieldTypesCallbacks()
    {
        $types = array();
        $numFields = $this->numFields();
        for( $i = 0; $i < $numFields; $i++ ) {
            $postgresType = pg_field_type( $this->resource, $i );
            if( true ) {
                try {
                    $converter = $this->db->converterFactory->getConverter($postgresType);
                } catch ( NoConverterFound $e ) {
                    // echo "Column {$column}\n";
                    // print_r( $this->query );
                    throw $e;
                }
            } else {
                $converter = TypeConversionFactory::get( $postgresType, $this );
            }
            $types[pg_field_name( $this->resource, $i )] = $converter;
        }
        return $types;
    }

    /**
     * Caching wrapper for pg_num_fields()
     * @return int
     */
    public function numFields()
    {
        if( !isset( $this->numFields ) ) {
            $this->numFields = pg_num_fields( $this->resource );
        }
        return $this->numFields;
    }

    /**
     * Caching wrapper for pg_num_rows()
     * @return int
     */
    public function numRows()
    {
        if( !isset( $this->numRows ) ) {
            $this->numRows = pg_num_rows( $this->resource );
        }
        return $this->numRows;
    }

    /**
     * Countable interface for numRows()
     * @return int
     */
    public function count()
    {
        return $this->numRows();
    }

    /**
     * Caching wrapper for pg_affected_rows()
     * This appears bugged in php 5.3 and postgres 9.1 returning the number of rows modified by a statement
     * @return int
     */
    public function numAffectedRows()
    {
        if( !isset( $this->affectedRows ) ) {
            $this->affectedRows = pg_affected_rows( $this->resource );
        }
        return $this->affectedRows;
    }

    // Itertor interfaces

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        pg_result_seek( $this->resource, 0 );
        $this->fetchRowNumber = 0;
        return $this;
    }

    /**
    * Return the current element
    */
    public function current()
    {
        return call_user_func(
            $this->fetchCallback,
            pg_fetch_array( $this->resource, $this->fetchRowNumber, $this->fetchType )
        );
    }

    /**
    * Return the key of the current element
    */
    public function key()
    {
        return $this->fetchRowNumber;
    }

    /**
    * Move forward to next element
    */
    public function next()
    {
        $this->fetchRowNumber++;
    }

    /**
    * Checks if current position is valid
    */
    public function valid()
    {
        return $this->fetchRowNumber < $this->numRows();
    }

    // Array access interface

    /**
     * Set the value at specified index to entity
     */
    public function offsetSet( $index, $entity )
    {
        throw new ResourcesAreReadonlyException();
    }

    /**
    * Return whether the requested index exists.
    */
    public function offsetExists( $index )
    {
        return $index >= 0 and $index < $this->numRows();
    }

    /**
    * Unsets the value at the specified index.
    */
    public function offsetUnset( $index )
    {
        throw new ResourcesAreReadonlyException();
    }

    /**
    * Returns the value at the specified index.
    */
    public function offsetGet( $index )
    {
        return call_user_func(
            $this->fetchCallback,
            pg_fetch_array( $this->resource, $index, $this->fetchType )
        );
    }

}