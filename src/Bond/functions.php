<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A collection of global functions that are always included / available
 */
namespace Bond {

    use Bond\Exception\BadTypeException;
    use Bond\InvalidArgumentException;

    /**
    * Check multiple keys exist in an array. Takes an unlimited number
    * of arguments collected using func_get_args, with the last argument
    * being the haystack to search.
    *
    * @param mixed $key(s)
    * @param mixed $array
    * @return boolean
    */
    function array_keys_exist()
    {

        if ( func_num_args() < 2) {
            throw new InvalidArgumentException( "expect at least 2 arugments" );
        }

        $args = func_get_args();
        $haystackArray = array_pop($args);

        if( !is_array( $haystackArray ) ) {
            throw new BadTypeException( $haystackArray, 'array' );
        }

        $keys = [];

        foreach( $args as $arg ) {
            if( is_array( $arg ) ) {
                $keys = array_merge( $keys, array_values( $arg ) );
            } else {
                $keys[] = $arg;
            }
        }

        return !(bool) array_diff_key( array_flip( $keys ), $haystackArray );

        // original test, fails if the key is duplicated
        return count( array_intersect( array_keys($haystack), $keys) ) === count($keys);

    }

    /**
     * Returns true if the callback returns true when applied to every member of a array
     *
     * @param mixed A callack of the type that would be accepted by call_user_func
     * @param array Array we're testing
     * @param booly The return value we exect if the passed array is empty
     * @return bool
     */
    function array_check( $callback, array $array, $passOnEmpty = true )
    {

        if( !$array ) {
            return $passOnEmpty;
        }

        $pass = true;
        reset( $array );
        while( $pass and list(,$value) = each($array) ) {
            $pass = ( $pass and call_user_func( $callback, $value ) );
        }

        return $pass;

    }

    /**
     * Is interish? Ie the string "100" is int-ish but "100.0" isn't
     * @param mixed
     * @return bool
     */
    function is_intish( $input )
    {
        return is_int( $input ) or ( (string) $input === (string) (int) $input );
    }

    /**
     * If this value is something that looks null-a-like, return null
     * That is to say, '', '   ', null all return null
     */
    function nullify( $value )
    {
        if( !is_scalar( $value ) ) {
            return $value;
        }

        if( strlen( trim( $value ) ) > 0 ) {
            return $value;
        }
        return null;
    }

    /**
     * Test a bool for not being null. Useful for array filter where you want to strictly null values
     * and not '' or 0
     * @param mixed $value
     * @return bool
     */
    function is_not_null( $value )
    {
        return $value !== null;
    }

    /**
     * Return a object from a serialized string.
     * Warning. This is potentially unsafe as it makes use of the 'newInstanceWithoutConstructor' reflection method.
     * See, http://www.php.net/manual/en/reflectionclass.newinstancewithoutconstructor.php
     * As such a malicious attacker could construct a object in a fucked state or instantiate a object it really shouldn't.
     * Make sure serialized objects never go anywhere near a user!
     */
    function unserialize_t( array $serialization )
    {

        // TODO it might be possible to check if the passed class name implements the required trait

        // instantiate object
        $refl = new \ReflectionClass( $serialization[0] );
        $obj = $refl->newInstanceWithoutConstructor();

        $reflMethod = $refl->getMethod('fromArray');
        $reflMethod->setAccessible(true);
        $reflMethod->invoke( $obj, $serialization[1] );

        return $obj;
    }

    /**
     * Are we being called from the command line.
     * http://php.net/manual/en/features.commandline.php
     * @return bool
     */
    function is_command_line()
    {
        return PHP_SAPI === 'cli';
    }

    /**
     * Return the last (unqualified) part of a class
     * @param object|string $objectOrString
     * @return string
     */
    function get_unqualified_class( $objectOrString )
    {

        $class = is_object( $objectOrString ) ? get_class( $objectOrString ) : (string) $objectOrString;

        if (false !== $nsSep = strrpos($class, '\\')) {
            return substr($class, $nsSep + 1);
        }
        return $class;

    }

    /**
     * Return the namespace part of a class definition
     * @param object|string $objectOrString
     * @return string
     */
    function get_namespace( $objectOrString )
    {
        $unqualifiedClass = is_string( $objectOrString ) ? $objectOrString : get_class( $objectOrString );
        return rtrim(
            substr(
                $unqualifiedClass,
                0,
                -strlen( get_unqualified_class( $unqualifiedClass ) )
            )
            , '\\'
        );
    }

    /**
     * Evaluate a boolean value "true" or "false" to the correct boolean type.
     * @param mixed $var
     * @return bool
     */
    function boolval($var, $default = false)
    {

        if( is_bool($var) ) {
            return $var;
        }

        if( strlen($var) > 0 ) {
            return ($var[0] == 'f' || $var[0] == 'F' || $var[0] == 'n' || $var[0] == 'N')
                 ? false
                 : (bool) $var
                 ;
        }

        return (bool) $default;

    }

    /**
     * Camelize a name. Version 2 based on Symfony2's camelize() found in Symfony\Component\DependencyInjection\Container
     * modified to collapse words
     * @param string $name
     * @return string
     */
    function mixed_case( $name )
    {

        $name = preg_replace_callback(
            '/(^|_|\\s|\.)+(.)/',
            function ($match) {
                return ('.' === $match[1] ? '_' : '').strtoupper($match[2]);
            },
            trim( trim( $name ), '_' )
        );

        // if the word isn't all uppercase
        if( strtoupper( $name ) !== $name ) {
            $name[0] = strtolower( $name[0] );
        }

        return $name;

    }

    /**
     * Convert a variable to pascal case
     * @param string $name
     */
    function pascal_case( $name )
    {
        return ucwords( \Bond\mixed_case( $name ) );
    }

    /**
     * Convert camcelCase into underscore_formatted_text
     * @param string $name
     * @return type
     * @author Joseph
     */
    function underscore_case( $name )
    {
        $name = preg_replace( '/([a-z])([A-Z])/', '$1_$2', trim($name, '_'));
        return strtolower($name);
    }

    /**
     * Convert camelCase string into separate words.
     * @param string $string
     * @return type
     * @author Joseph
     */
    function word_case( $string )
    {
        return preg_replace( '/([a-z])([A-Z])|_/', '$1 $2', $string );
    }

    /**
     * Extract tags from string
     *
     * Tags beginning with %tag: [1,2,3] are json decoded
     * Tags beginning with @tag: value are build in the stanard way
     * Tags can be 'namespaced' with '.'
     *
     * @param string|object String from which to extract comments. If object it must expose ->getComment(). // If you want inheritance it must expose ->getReferences()
     * @return array Comments
     */
    function extract_tags( $stringOrObject, $prefix = null )
    {

        // object handling
        if( is_object( $stringOrObject ) ) {
            if( method_exists($stringOrObject, 'getComment') ) {
                $string = $stringOrObject->getComment();
            } else {
                throw new BadTypeException($stringOrObject, 'string');
            }
        } else {
            $string = (string) $stringOrObject;
        }

        $regex = "/^
                    ([%@$])
                    ([^\\v:\[\]]+)
                    (\[
                       [^\]]*
                     \])?
                    :
                    (.*)?
                   $
                   |
                   ^
                    ([%@])
                    (.*)
                   $
                  /mx";

        $output = array();

        if( preg_match_all( $regex, $string, $matches, PREG_SET_ORDER ) ) {

            foreach( $matches as $match ) {

                // we got a lone tag no value
                if( isset( $match[6] ) ) {
                    $match[2] = $match[6];
                    $match[4] = 'true';
                }

                $key = trim($match[2]);
                $value = trim($match[4]);

                // Namespacing. Fucking hell these were 6 very difficult lines. Be afraid. Seriously. I'm not joking.
                $working = array();
                $refs = array();
                $keys = explode('.',$key);
                foreach( array_reverse( $keys ) as $k ) {
                    $working = array( $k => $working );
                    $refs[] =& $working[$k];
                }

                // inheritance?
                if( $value === '@inherit' ) {

                    throw new \Exception("TODO");

                    if( !is_object( $stringOrObject) ) {
                        throw new \LogicException( "can't inherit off object which doesn't reference another object, see object->get('references')" );
                    }

                    // merge only the tags which are required
                    if( $reference = $stringOrObject->get('references') ) {
                        $value = \Bond\extract_tags( $reference );
                        foreach( $keys as $key ) {
                            // no match
                            if( !is_array( $value ) || !array_key_exists( $key, $value ) ) {
                                // break reference chain. This loop will now do nothing.
                                $working = array();
                            } else {
                                $value = $value[$key];
                            }
                        }
                    } else {
                        // break reference chain. This loop will now do nothing.
                       $working = array();
                   }

                }

               // json decode
                if( $match[1] === '%' ) {
                    $value = json_decode( $value, true );
                    if( json_last_error() ) {
                        throw new \UnexpectedValueException( "json_decode returned a error when extracting tags `{$value}` in \n `{$string}`\n" );
                    }
                } elseif( $match[1] === '$' ) {
                    $value = call_user_func( $value );
                }

                // cast bool-like strings and nulls to their real types
                if( is_string( $value ) ) {
                    if( in_array( strtolower( $value ), array( 't', 'f', 'true', 'false', 'on', 'off' ) ) ) {
                        $value = \Bond\boolval( $value );
                    } elseif( $value === 'NULL' ) {
                        $value = 'spanner';
                    }
                }

                // is array()
                if( !empty($match[3]) ) {
                    if( $match[3] == '[]' ) {
                        $refs[0][] = $value;
                    } else {
                        $key = trim( $match[3], '[]' );
                        $refs[0][$key] = $value;
                    }
                } else {
                    $refs[0] = $value;
                }

                // merge working into output
                $output = array_merge_recursive( $output, $working );

            }

        }

        // prefix namespace aware
        if( isset($prefix) ) {
            foreach( explode( '.', $prefix ) as $key ) {
                if( !is_array( $output ) || !array_key_exists( $key, $output ) ) {
                    return array();
                } else {
                    $output = $output[$key];
                }
            }
        }

        return $output;

    }

}