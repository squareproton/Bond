<?php

namespace Bond\Pg;

use Bond\Pg;

// Based on work by Grégoire HUBERT
// Please see, https://github.com/chanmix51/Pomm/blob/1.1/Pomm/Connection/Database.php
/**
 * Pomm\Connection\Database
 *
 * @package Pomm
 * @version $id$
 * @copyright 2011 Grégoire HUBERT
 * @author Grégoire HUBERT <hubert.greg@gmail.com>
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class TypeConverterFactory
{

    private $converters = [];

    private $db;

    public function __construct( Pg $db )
    {
        $this->db = $pg;

//        $this->register( new Converter\PgArray($this), array());
        $this->register( new Converter\PgBoolean(), array('bool'));
        $this->register( new Converter\PgNumber(), array('int2', 'int4', 'int8', 'numeric', 'float4', 'float8', 'oid', 'oidvector'));
        $this->register( new Converter\PgString(), array('varchar', 'char', 'text', 'citext', 'uuid', 'tsvector', 'xml', 'bpchar', 'json', 'name', 'int2vector', 'pg_node_tree'));

//        $this->register( new Converter\PgTimestamp(), array('timestamp', 'date', 'time'));
//        $this->register( new Converter\PgInterval(), array('interval'));
//        $this->register( new Converter\PgBytea(), array('bytea'));
//        $this->register( new Converter\PgNumberRange(), array('int4range', 'int8range', 'numrange'));
//        $this->register( new Converter\PgTsRange(), array('tsrange', 'daterange'));
//       switch( true ) {
//
//            case $type === 'bool':
//                return new Bool('bool');
//
//            case $type === 'oid':
//            case $type === 'int4':
//            case $type === 'int8':
//
//            # exotic from here down
//            case $type === 'int2':
//            case $type === 'oidvector':
//                return new NullSafeCallback( $type, 'intval');
//
//            case substr( $type, 0, 4 ) === '_int':
//                return new GenericArray( $type, true );
//
//            case $type === 'citext':
//            case $type === 'text':
//            case $type === 'char':
//            case $type === 'name':
//            # exotic from here down
//            case $type === 'int2vector':
//            case $type === 'pg_node_tree': # seems to be stored internally as text so pass it off
//                return new NullSafeCallback( $type, 'strval');
//
//            case $type === 'bit':
//            case $type === 'varbit':
//                return new NullSafeCallback( $type, 'bindec');
//
//            case $type === 'bytea':
//                return new NullSafeCallback( $type, 'pg_unescape_bytea');
//
//            case $type === 'oid':
//                return new NullSafeObjectInstantiation( $type, '\\Bond\\Entity\\Types\\Oid', [$result->db] );
//
//            case $type === 'timestamp':
//                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\DateTime");
//
//            case $type === 'json':
//                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\Json", [Json::VALIDATE_DISABLE] );
//
//            case $type === 'inet':
//                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\Inet");
//
//            case $type === 'hstore':
//                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\Hstore");
//
//            case $type === 'interval':
//                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\DateInterval");
//
//            case  $type === 'tsrange':
//                return new DateRange( $type );
//
//            case $type === 'StockState':
//                return new StockState( $type );

    }

    public function register( ConverterInterface $converter, array $types )
    {
        foreach( $this->types as $type ) {
            $this->converters[$type] = $converter;
        }
    }

    public function getConverter( $type )
    {
        if( isset( $this->converters[$type] ) ) {
            return $this->converters[$type];
        }
        throw new \Exception("Unknown type `{$type}`");
    }

}