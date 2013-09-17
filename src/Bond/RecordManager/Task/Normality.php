<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\Task;

use Bond\Pg;
use Bond\Sql\Modifier;
use Bond\Sql\Query as PgQuery;
use Bond\Sql\QuoteInterface;
use Bond\Pg\Result;
use Bond\Sql\Constant;

use Bond\RecordManager\Task;
use Bond\RecordManager\ChainSavingInterface;

use Bond\RecordManager\Exception\NothingToDoException;

abstract class Normality extends Task
{

    /**
     * Class constants
     */
    const UPDATE_COLLECTION = '__QUERY_UPDATE_COLLECTION';
    const ACTION_OBJECT = '__ACTION_OBJECT';

    /**
     * Debugging help
    public function execute( $action )
    {
        return;
        echo sprintf(
            "%s: %s %s\n",
            get_called_class(),
            $action,
            get_class( $this->object )
        );
    }
     */

    /**
     * The is compatible check might be done a __lot__. Cache the results.
     * @var array
     */
    private static $cacheIsCompatible = array();

    /**
     * Generate a tuple of the the keys in a data array. Eg, ('id','line1','line2',...'postcode')
     * @param array $data
     * @return string
     */
    private static function keyTuple( QuoteInterface $quoting, array $data )
    {
        list($firstKey,) = each($data);
        $keys = implode(
            ",",
            array_map(
                array( $quoting, 'quoteIdent'),
                array_keys( $data[$firstKey] )
            )
        );
        return "({$keys})";
    }

    /**
     * Generate tuples from data in array. Eg, (123,'Omlet HQ','Tuthill Park',...,'OX17 1SD')
     * @param array $data
     * @return string
     */
    private static function dataTuples( array $data )
    {
        return implode(
            ',',
            array_map(
                function($row){
                    return "(".implode(',',$row).")";
                },
                $data
            )
        );
    }

    /**
     * Generate tuples from data in array. Eg, (123,'Omlet HQ','Tuthill Park',...,'OX17 1SD') and a additional array
     * This is used to build up a more longer tuple which might be required in, say, a UPDATE statement
     * @param array $data
     * @param array $dataInitial
     * @return string
     */
    private static function dataTuplesInitial( array $data, array $dataInitial )
    {

        return implode(
            ',',
            array_map(
                function( $row, $initial ){
                    return "(".
                        implode(
                            ',',
                            array_merge(
                                array_values($row),
                                array_values($initial)
                            )
                         ).
                         ")";
                },
                $data,
                $dataInitial
            )
        );
    }

    /**
     * Generate a select statement from a data array.
     * Essentially equivalent to VALUES(),(),() statements but this seems to be more type safe and we can name columns.
     *
     * !!!! WARNING !!!! Proof of concept. NOT finished !!!!
     *
     * @param array $data
    private static function dataSelectTupleAlternatives( array $data, &$columns = array() )
    {

        if( $data ) {
            $columns = array_keys( $data[0] );
        }

        $output = array();
        foreach( $data as $row ) {
            $_row = array();
            foreach( $row as $column => $value ) {
                $_row[] =  sprintf( "%s AS %s", $value, PgQuery::quoteIdentifier( $column ) );
            }
            $output[] = "SELECT " . implode( ', ', $_row );
        }
        return implode( " UNION \n", $output );

        die();
        return implode(
            " UNION \n",
            array_map(
                function($row){
                    return "SELECT " . implode(',',$row);
                },
                $data
            )
        );
    }
     */

    /**
     * Delete query for a data array
     * @param string Table (unquoted)
     * @param array of data
     */
    public static function buildQueryDelete( QuoteInterface $quoting, $table, array $data, array $returning = null )
    {

        if( !count($data) === 0 ) {
            throw new NothingToDoException("Nothing to generate.");
        }

        $keys = self::keyTuple( $quoting, $data );
        $tuples = self::dataTuples( $data );

        return sprintf(
            "DELETE FROM %s WHERE (%s) IN (%s) %s;",
            $quoting->quoteIdent( $table ),
            $keys,
            $tuples,
            static::getReturningClause( $quoting, $returning )
        );

    }

    /**
     * Update query for a data array
     * @param string $table
     * @param array $primaryKeys
     * @param array $data
     * @return string SQL insert statement
     */
    public static function buildQueryUpdate( QuoteInterface $quoting, $table, array $primaryKeys, array $data, array $dataInitial, array $returning = null )
    {

        if( !count($data) === 0 ) {
            throw new NothingToDoException("Nothing to generate.");
        }

        $columnNames = array_keys( $data[0] );
        $columnNamesInitial = array_keys( $dataInitial[0] );

        // select statement
        $dataAsSql = "VALUES " . (
            $columnNamesInitial
                ? self::dataTuplesInitial( $data, $dataInitial )
                : self::dataTuples( $data )
        );

        $columns = array();
        $where = array();

        // core data
        foreach( array_keys( $data[0] ) as $key => $column ) {

            $sqlFragment = sprintf(
                '%s = "uc"."%s"',
                $quoting->quoteIdent( $column ),
                $columnNames[$key]
            );

            $columns[] = $sqlFragment;

            if( array_key_exists( $column, $primaryKeys ) ) {
                $where[$column] = '"t".'.$sqlFragment;
            }

        }

        // dataInitial
        // all dataInitial columns are primary keys, see self::extractColumnInfoFromDataTypes()
        foreach( array_keys( $dataInitial[0] ) as $key => $column ) {

            // overwrite existing where definition
            $where[$column] = sprintf(
                '"t".%s = "uc"."__initial_%s"',
                $quoting->quoteIdent( $column ),
                $columnNames[$key]
            );

            $columnNames[] = "__initial_{$column}";

        }

        if( !count($columns) ) {
            throw new NothingToDoException("Nothing to update for `{$table}`. Every column passed in the data array is a primary key column.");
        }

        return sprintf( <<<SQL
UPDATE
    %s AS "t"
SET
    %s
FROM
    (
       %s
    ) AS uc ( "%s" )
WHERE
    %s
%s;
SQL
,
            $quoting->quoteIdent( $table ),
            implode( ', ', $columns ),
            $dataAsSql,
            implode( '", "', $columnNames ),
            implode( ' AND ', $where ),
            '' #static::getReturningClause( $returning, 't' )
        );

    }

    /**
     * Build insert query
     * @param string $table
     * @param array $data
     * @return string SQL insert statement
     */
    public static function buildQueryInsert( QuoteInterface $quoting, $table, array $data, array $returning = null )
    {

        if( !count($data) === 0 ) {
            throw new NothingToDoException("Nothing to generate.");
        }

        $keys = self::keyTuple( $quoting, $data );
        $tuples = self::dataTuples( $data );

        return sprintf(
            "INSERT INTO %s %s VALUES %s %s;",
            $quoting->quoteIdent( $table ),
            $keys,
            $tuples,
            static::getReturningClause( $quoting, $returning )
        );

    }

    /**
     * Turn a array of columns into a comma separated identifier list suitable for being passed into a RETURNING
     * @param array $returning
     * @return string
     */
    protected static function getReturningClause( QuoteInterface $quoting, array $returning = null, $table = '' )
    {
        if( $returning === null ) {
            return 'RETURNING *';
        } elseif ( $returning === array() ) {
            return '';
        } elseif( !empty( $table ) ) {
            foreach( $returning as &$value ) {
                $value = "{$table}.{$value}";
            }
        }

        return "RETURNING ". implode(
            ',',
            $returning = array_map(
                array( $quoting, 'quoteIdent' ),
                $returning
            )
        );
    }

    /**
     * Validate a array of query datas arrays
     *
     * @param array datas array of datas
     * @param array validation callback
     */
    protected static function validateQueryDatas( array &$datas, array $modifiers )
    {
        foreach( $datas as $key => &$data ) {
            self::validateQueryData( $data, $modifiers );
        }
    }

    /**
     * Validate a query datas array
     *
     * @param array query datas array
     * @param array validation callback
     */
    protected static function validateQueryData( array &$data, array $modifiers )
    {
        foreach( $data as $column => &$value ) {
            $value = $modifiers[$column]->exec( $value );
        }
    }

    /**
     * Extract information from a entities dataTypes into a format useful for self::buildQueryDataFromEntity
     *
     * @param array $dataTypes
     * @param array $modfiers array( 'column_name' => modifier() )
     * @param array $sequences
     * @param array $defaults
     *
     */
    protected static function extractColumnInfoFromDataTypes(
        $dataTypes,
        QuoteInterface $db,
        &$modifiers,
        &$modifiersInitial = array(),
        &$sequences = array(),
        &$defaults = array(),
        $cast = false
    )
    {

        $modifiers = array();
        $modifiersInitial = array();
        $sequences = array();
        $defaults = array();

        foreach( $dataTypes as $name => $dataType ) {

            $modifiers[$name] = $dataType->getQueryModifier( $db, $cast );

            // we got initial data we need to check
            if( $dataType->isInitialProperty() and $dataType->isPrimaryKey() ) {
                $modifiersInitial[$name] = $modifiers[$name];
            }

            // we got default?
            if( $default = $dataType->getDefault() ) {

                if( $dataType->isSequence( $sequenceName ) ) {
                    $sequences[$name] = $sequenceName;
                } else {
                    $defaults[$name] = $default;
                }

            }

        }

    }

    /**
     * Get chain tasks
     * @param Bond\Entity\Base $entity
     * @param array $chainTasks
     * @param array $columns
     * @return null
     */
    protected static function buildChainTasks( \Bond\Entity\Base $entity, $columns )
    {

        $tasks = array();

        foreach( $columns as $column ) {

            $value = $entity->get($column);

            // chain saving
            if( is_object($value) and $value instanceof ChainSavingInterface ) {
                $value->chainSaving( $tasks, $column );
            }

        }

        return $tasks;

    }

    /**
     * Get a associative array( columnNames => escapedValue )
     * @param Bond\Entity\Base $entity
     * @param array $columns
     * @param array $sequences
     * @param array $defaults
     * @param mixed $source
     * @param Bond\RecordManager\Task $task
     * @return array
     */
    protected static function buildQueryDataFromEntity( $entity, $columns, $sequences = array(), $defaults = array(), $source = \Bond\Entity\Base::DATA, \Bond\RecordManager\Task $task = null )
    {

        $data = array();

        foreach( $columns as $column ) {

            $value = $entity->get( $column, null, $source, $task );

            // default values
            if( is_null( $value ) and ( isset( $defaults[$column] ) or isset( $sequences[$column] ) ) ) {
                $value = new Constant('DEFAULT');
            }

            $data[$column] = $value;

        }

        return $data;

    }

    /**
     * Get the current (ie, value most recently obtained) from a list of named Sequences
     *
     * @param array $sequences array of sequence names
     * @param Pg $db Database connection to use
     * @return array Array of sequence values
     */
    protected static function getSequenceCurval( array $sequences, Pg $db )
    {

        // anything to do
        if( !$sequences ) {
            return array();
        }

        // sequence components
        foreach( $sequences as $key => &$sequence ) {

            $sequence = sprintf(
                "currval('%s'::regclass) as %s",
                $sequence,
                Query::quoteIdentifier( $key )
            );

        }
        $sequences = implode(', ', $sequences);

        return $db->query( new Query( "SELECT {$sequences};" ) )->fetchSingle( Result::FLATTEN_PREVENT );

    }

    /**
     * Does this object present the required methods to be automatically saved by RecordManager
     *
     * To be persit-able a object must expose the following public methods
     *
     * $obj->isNew()
     * $obj->isChanged()
     * $obj->isValid() -- not done yet
     *
     * @param mixed Object
     * @return array|null (booly)
     */
    public static function isCompatible( $object, &$error = null )
    {

        if( !is_object( $object ) ) {
            throw new \InvalidArgumentException("Only objects are normality compatible.");
        }

        $class = get_class( $object );

        // we got this in our cache?
        if( isset( self::$cacheIsCompatible[$class] ) ) {
            if( self::$cacheIsCompatible[$class] === true ) {
                $error = '';
                return true;
            }
            $error = self::$cacheIsCompatible[$class];
            return false;
        }

        // variable setup
        $error = array();
        $output = true;

        // isNew, isChanged
        foreach( array('isChanged','isReadonly','isZombie','checksumReset','setDirect','r') as $function ) {
            $$function = array( $object, $function );
            if( !\is_callable( $$function ) ) {
                $output = false;
                $error[] = sprintf(
                    "Can't reach %s->%s()",
                    get_class( $object ),
                    $function
                );
            }
        }

        $error = implode("\n", $error);

        // add to cache
        self::$cacheIsCompatible[$class] = $output ? true : $error;

        return $output;

    }

}