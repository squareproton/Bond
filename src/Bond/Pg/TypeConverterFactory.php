<?php

namespace Bond\Pg;

use Bond\Pg;
use Bond\Pg\Converter\ConverterInterface;

use \ReflectionClass;

use Bond\Entity\Types\DateTime;
use Bond\Entity\Types\Inet;
use Bond\Entity\Types\Hstore;
use Bond\Entity\Types\DateInterval;
use Bond\Entity\Types\Json;
use Bond\Entity\Types\Oid;

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
        $this->db = $db;

        $this->register( new Converter\PgBoolean(), array('bool'));
        $this->register( new Converter\PgNumber(), array('int2', 'int4', 'int8', 'numeric', 'float4', 'float8', 'oid', 'oidvector'));
        $this->register( new Converter\PgString(), array('varchar', 'char', 'text', 'citext', 'uuid', 'tsvector', 'xml', 'bpchar', 'json', 'name', 'int2vector', 'pg_node_tree'));
        $this->register( new Converter\PgBitString(), array('bit', 'varbit') );
        $this->register( new Converter\PgBytea(), array('bytea') );

        // this will need to be changed somewhat
        $this->register( new Converter\NullSafeObjectInstantiation( new \ReflectionClass(DateTime::class) ), ['timestamp'] );
        $this->register( new Converter\NullSafeObjectInstantiation( new \ReflectionClass(Inet::class) ), ['inet'] );
        $this->register( new Converter\NullSafeObjectInstantiation( new \ReflectionClass(Hstore::class) ), ['hstore'] );
        $this->register( new Converter\NullSafeObjectInstantiation( new \ReflectionClass(DateInterval::class) ), ['interval'] );
//        $this->register( new Converter\NullSafeObjectInstantiation( new \ReflectionClass(Oid::class), [$this->db] ), ['oid'] );
        $this->register( new Converter\NullSafeObjectInstantiation( new \ReflectionClass(Json::class), [Json::VALIDATE_DISABLE] ), ['json'] );

        $this->register( new Converter\DateRange(), array('tsrange') );

//        $this->register( new Converter\PgTimestamp(), array('timestamp', 'date', 'time'));
//        $this->register( new Converter\PgInterval(), array('interval'));
//        $this->register( new Converter\PgNumberRange(), array('int4range', 'int8range', 'numrange'));
//        $this->register( new Converter\PgTsRange(), array('tsrange', 'daterange'));


    }

    public function register( ConverterInterface $converter, array $types )
    {
        foreach( $types as $type ) {
            $this->converters[$type] = $converter;
        }
    }

    public function getConverter( $type )
    {
        // vanilla type
        if( isset( $this->converters[$type] ) ) {
            return $this->converters[$type];

        // array type
        } elseif( 0 === strpos( $type, '_' ) ) {
            $type = substr($type, 1);
            $baseConverter = $this->getConverter($type);
            return new Converter\PgArray($baseConverter);
        }
        throw new Exception\NoConverterFound($type);
    }

}