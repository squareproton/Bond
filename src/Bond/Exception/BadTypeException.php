<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class BadTypeException extends \Exception
{

    public $var;
    public $type;

    /**
     * @param mixed Variable who's type we object to
     * @param string|object The type we'd have liked $var to have been
     */
    public function __construct( $var, $type )
    {

        $this->var = $var;
        $this->type = $type;

        // if type is a object determine it's class
        $type = is_object( $type ) ? get_class( $type ) : $type;

        parent::__construct(
            sprintf(
                "Object of type `%s` expected. Passed `%s`",
                $type,
                $this->getVarForDisplay()
            )
        );

    }

    /**
     * Get a human readable representation of the passed varibale
     * @return string
     */
    public function getVarForDisplay()
    {

        if( is_object( $this->var ) ) {
            $output = "object ". get_class( $this->var );
        } elseif( is_string( $this->var ) or is_array( $this->var ) ) {
            $type = is_string( $this->var ) ? 'string' : 'array';
            $working = json_encode($this->var);
            $length = strlen( $working );
            if( $length > 50 ) {
                $output = sprintf(
                    "%s %s ... %s chars ommited",
                    $type,
                    substr( $working, 0, 40 ),
                    $length - 40
                );
            } else {
                $output = "{$type} {$working}";
            }
        } elseif( is_null( $this->var ) ) {
            $output = "NULL";
        } elseif( is_bool( $this->var ) ) {
            $output = $this->var ? "TRUE" : "FALSE";
        } elseif( is_int( $this->var ) ) {
            $output = "int {$this->var}";
        } elseif( is_float( $this->var ) ) {
            $output = "float {$this->var}";
        } elseif( is_resource( $this->var ) ) {
            $output = "resource `" .get_resource_type( $this->var ) . "`";
        } else {
            $output = print_r( $this->var, true );
        }

        return $output;

    }

}