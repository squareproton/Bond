<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg;

use Bond\Database\TypeConversion\NullSafeCallback;
use Bond\Database\TypeConversion\NullSafeObjectInstantiation;
use Bond\Pg\TypeConversion\DateRange;
use Bond\Pg\TypeConversion\GenericArray;
use Bond\Pg\TypeConversion\StockState;
use Bond\Pg\TypeConversion\Bool;

use Bond\Pg\Exception\UnknownPostgresType;

use Bond\Repository;
use Bond\Entity\Types\Json;

class TypeConversionFactory
{

    /**
     * Return a instanceof TypeConversionInterface which can converts (named) postgres type to a php value of the correct type
     *
     * @param string fieldType, see, http://www.postgresql.org/docs/8.4/static/datatype.html
     * @return Bond\Pg\TypeConversionInterface
     */
    public static function get( $type, Result $result )
    {

        switch( true ) {

            case $type === 'bool':
                return new Bool('bool');

            case $type === 'oid':
            case $type === 'int4':
            case $type === 'int8':

            # exotic from here down
            case $type === 'int2':
            case $type === 'oidvector':
                return new NullSafeCallback( $type, 'intval');

            case substr( $type, 0, 4 ) === '_int':
                return new GenericArray( $type, true );

            case $type === 'citext':
            case $type === 'text':
            case $type === 'char':
            case $type === 'name':
            # exotic from here down
            case $type === 'int2vector':
            case $type === 'pg_node_tree': # seems to be stored internally as text so pass it off
                return new NullSafeCallback( $type, 'strval');

            case $type === 'bit':
            case $type === 'varbit':
                return new NullSafeCallback( $type, 'bindec');

            case $type === 'bytea':
                return new NullSafeCallback( $type, 'pg_unescape_bytea');

            case $type === 'oid':
                return new NullSafeObjectInstantiation( $type, '\\Bond\\Entity\\Types\\Oid', [$result->db] );

            case $type === 'timestamp':
                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\DateTime");

            case $type === 'json':
                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\Json", [Json::VALIDATE_DISABLE] );

            case $type === 'inet':
                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\Inet");

            case $type === 'hstore':
                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\Hstore");

            case $type === 'interval':
                return new NullSafeObjectInstantiation( $type, "\\Bond\\Entity\\Types\\DateInterval");

            case  $type === 'tsrange':
                return new DateRange( $type );

            case $type === 'StockState':
                return new StockState( $type );

            case $type[0] === '_':
                return new GenericArray( $type, false);

            // entity dataTypes
//            case Repository::isEntity( substr( $type, 1 ) ):
//
//                return new Repository( substr( $type, 1 ) );

            // some different strategies for handling unknown types
            default:

                print_r($type);

                return new NullSafeCallback( $type, 'strval');

                /*
                return new LegacyCallback( $type, function( $value ) use ( $type ) {
                    echo "{$type} - {$value}\n";
                    return new LegacyCallback( $type, (string) $type);
                });
                */

                throw new UnknownPostgresType( "`{$type}`: You might consider expanding this switch statement." );

        }

    }

}