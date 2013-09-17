<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

use Bond\Exception\BadPropertyException;
use Bond\Exception\BadTypeException;
use Bond\Exception\DepreciatedException;
use Bond\Database\Exception\MissingArgumentException;

use Bond\Sql\Identifier;
use Bond\Sql\Modifier;
use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlCollectionInterface;
use Bond\Sql\SqlQueryBase;

/**
 * Class to manage the creation of SQL queries.
 * It aims to provide a safe and easy way to write sql proof code. Escaping by default.
 *
 * Take a raw sql, find and extract the named placeholders.
 * Replace with a type validated input.
 *
 * sql tokenisation standard
 *
 *     %id:int%                   -> validate the named argument `id` as a integers
 *     %first_name:text|null%     -> validate the named argument `first_name` as a text string. If empty replace with null
 *     %last_name:char(1)|null%   -> validate the named argument `last_name` as a string of max length 1 char. If empty replace with null
 *     %create_date:date|null%    -> validate the named argument `date` as a date.
 *     %orders:int[]%             -> validate `orders` as a array of integers
 *     %orders:%                  -> validates the `orders` by determining it's type.
 *
 *
 *   INSERT INTO test( id, name ) values ( %id:int|null%, %name:text|null% );
 */
class Query extends SqlQueryBase
{

    /**
     * Class constants
     */
    const TEXT = 'text';
    const BOOL = 'bool';
    const ENUM = 'enum';
    const INT  = 'int';
    const NUMERIC = 'numeric';
    const UNKNOWN = null;

    /*
     * Apply our data variables to the sql. Escape as required.
     * @return string. A hopefully, 100% injection proof sql statement
     */
    public function parse( QuoteInterface $quoting )
    {

        // store of subsituted variables
        $substitutions = [];

        // for closure
        // this might not be required for postgres 5.4
        $data = $this->data;

        $sql = preg_replace_callback(
            '/%
                ([a-zA-Z1-9_]+)
                :
                (?:
                  (
                   identifier|
                   in|
                   int|
                   oid|
                   text|
                   citext|
                   enum|
                   timestamp|
                   array|
                   json|
                   char\([\d]+\)|
                   varbit|
                   bool|
                   bytea
                  )?
                  (\\[\\])?
                )
                (?:\\|([^%]+))?
              %/x',
            function( $matches ) use ( $quoting, $substitutions, $data ) {

                $namedKey = $matches[1];

                // check we got everything we need
                if( !array_key_exists( $namedKey, $data ) ) {
                    throw new MissingArgumentException( "Query does not have the named property `{$namedKey}`." );
                }

                // this been done before?
                if( !array_key_exists( $namedKey, $substitutions ) ) {

                    $isArray = ( isset( $matches[3] ) and $matches[3] == '[]' );

                    // coerse to type
                    $coercedToType = \Bond\nullify( isset($matches[2]) ? $matches[2] : null );

                    $modifier = new Modifier(
                        $quoting,
                        $coercedToType,
                        $isArray
                    );

                    // casting and transformers
                    if( isset( $matches[4] ) ) {
                        $this->processStringTransformers( $matches[4], $modifier );
//                        $modifier->addFromString( $matches[4] );
                    }

                    // get the final value
                    $substitutions[$namedKey] = $modifier->exec( $data[$namedKey] );

                }

                return $substitutions[$namedKey];

            },
            $this->sqlGet()
        );

        return $sql;

    }

    /**
     * Helper method for the parse query regex callback which converts the a string like 'cast(text)|null' or '|null'
     * Into processoer callbacks for Modifier
     * @param string Transformer string we're going to process
     * @param Modifier $modifier
     */
    private function processStringTransformers( $string, Modifier $modifier )
    {

        // there can be multiple transformers are separated by |
        foreach( explode( '|', $string ) as $fragment ) {

            switch( true ) {

                case $fragment === 'null':

                    $modifier->add( 'pre', '\Bond\nullify' );
                    break;

                case substr( $fragment, 0, 4 ) === 'cast':

                    // extract type type from a modifier of the type cast(int), cast(text)
                    // default to the passed type if that doesn't work
                    $castTo = substr( $fragment, 5, -1 ) ?: $modifier->type;
                    // $castTo = $modifier->quoteInterface->quoteIdent( $castTo );

                    $modifier->add( 'post', $modifier->generateCastClosure( $castTo ) );
                    break;

                default:

                    throw new UnknownQueryModifier( $modifier );

            }

        }

    }

}