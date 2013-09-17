<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;

/**
 * @author Pete
 * Additional code taken from DmitryKoterov and the wonderful Postgres aware data converter.
 * See https://github.com/DmitryKoterov/db_type/blob/master/lib/DB/Type/Pgsql/Hstore.php and related work
 */
class Hstore extends \stdClass implements SqlInterface, \JsonSerializable, \Countable
{

    /**
     * See, http://www.postgresql.org/docs/9.2/static/hstore.html
     * @param mixed Array|string
     */
    public function __construct( $data = null )
    {
        if( is_string( $data ) ) {
            $data = $this->fromStringToArray( $data );
            // decode string assign properties
        }
        if( is_array( $data ) ) {
            foreach( $data as $key => $value ) {
                $this->$key = $value;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function count()
    {
        $c = 0;
        foreach( $this as $value ) {
            $c++;
        }
        return $c;
    }

    /**
     * Is this hstore empty
     * @return bool
     */
    public function isEmpty()
    {
        foreach( $this as $_v ) {
            return false;
        }
        return true;
    }

    /**
     * Compare two hstores
     */
    public function isSameAs( Hstore $compStore )
    {

        $thisArray = $this->jsonSerialize();
        ksort( $thisArray );

        $compStore = $compStore->jsonSerialize();
        ksort( $compStore );

        return json_encode( $thisArray ) == json_encode( $compStore );

    }

    /**
     * Get the string representation of this hstore
     * @return string. PostgresStyle!
     */
    public function __toString()
    {
        // iterate over object building string representation of pairs
        $fragments = [];
        foreach( $this as $key => $value ) {
            $fragments[] = sprintf(
                '%s=>%s',
                $this->escape( $key ),
                $this->escape( (string) $value )
            );
        }
        return implode( ',', $fragments );
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return array_map(
            'strval',
            (array) $this
        );
    }

    /**
     * Build a array from a postgres' hstore string representation
     * @param string
     * @return array
     */
    private function fromStringToArray( $str )
    {

        $p = 0;

        // Leading spaces.
        $c = $this->charAfterSpaces( $str, $p );
        if( $c === false ) {
            return array();
        }

        $result = [];

        while (true) {

            $c = $this->charAfterSpaces($str, $p);

            // end of string
            if ($c === false) {
                break;
            }

            // next element
            if ($c == ',') {
                $p++;
                continue;
            }

            // key
            $key = $this->readKeyValue($str, $p);

            // '=>' sequence.
            $this->charAfterSpaces($str, $p);
            if (substr($str, $p, 2) != '=>') {
                throw new \Exception("Was expecting a `=>` {$str} when passing hstore's value");
            }
            $p += 2;
            $this->charAfterSpaces($str, $p);

            // value, null safe
            $value = $this->readKeyValue($str, $p);
            if (!strcasecmp($value, "null")) {
                $result[$key] = null;
            } else {
                $result[$key] = $value;
            }

        }

        return $result;
    }

    /**
     * Move $p to skip spaces from position $p of the string.
     * Return next non-space character at position $p or
     * false at the string end.
     *
     * @param string $str
     * @param int $p
     * @return string
     */
    private function charAfterSpaces($str, &$p)
    {
        $p += strspn($str, " \t\r\n", $p);
        return substr($str, $p, 1);
    }

    /**
     * Read a 'key' or 'value' component from a hstore's string representation
     *
     * @param string $str
     * @param int $p
     * @return string
     */
    private function readKeyValue($str, &$p)
    {

        $c = substr($str, $p, 1);

        // Unquoted string.
        if ($c != '"') {
            $len = strcspn($str, " \r\n\t,=>", $p);
            $value = substr($str, $p, $len);
            $p += $len;
            // $value = stripcslashes($value)
            $value = str_replace( '\\"', '"', $value );
            $value = str_replace( '\\\\', '\\', $value );
            return $value;
        }

        // Quoted string.
        $m = null;
        if (preg_match('/" ((?' . '>[^"\\\\]+|\\\\.)*) "/Asx', $str, $m, 0, $p)) {
            $p += strlen( $m[0] );
            // $value = stripcslashes($m[1]);
            $value = str_replace( '\\"', '"', $m[1] );
            $value = str_replace( '\\\\', '\\', $value );
            return $value;
        }

        // Error.
        throw new \Exception( "quoted or unquoted string `{$str}` only" );

    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quotingInterface )
    {
        return $quotingInterface->quote( $this->__toString() );
    }

    /**
     * Escape a key or value according to the escaping spec, See, http://www.postgresql.org/docs/9.2/static/hstore.html
     * "Whitespace between pairs or around the => sign is ignored.
     * Double-quote keys and values that include whitespace, commas, =s or >s.
     * To include a double quote or a backslash in a key or value, escape it with a backslash."
     */
    private function escape( $value )
    {

        // nullsafe
        if( $value === null ) {
            return 'NULL';
        } elseif ( strcasecmp($value, "null") === 0 ) {
            return '"null"';
        }

        $hasBackslash = strpos( $value, '\\' ) !== false;
        $hasDoublequote = strpos( $value, '"' ) !== false;

        $hasWhitespaceEqualsCommaOrGreaterThan = (
            strpos( $value, ',' ) !== false or
            strpos( $value, '=' ) !== false or
            strpos( $value, '>' ) !== false or
            // the otherway of doing this test might be to use strcspn but I'm not sure how mb safe it is
            preg_match( '/\\s/x', $value )
        );

        // start building the output
        // not using php's addcslashes because of concern's it isn't mulitbyte safe
        if( $hasBackslash ) {
            $value = str_replace( '\\', '\\\\', $value );
        }

        if( $hasDoublequote ) {
            $value = str_replace( '"', '\\"', $value );
        }

        if( $hasWhitespaceEqualsCommaOrGreaterThan ) {
            $value = '"'.$value.'"';
        }

        return $value;

    }

}