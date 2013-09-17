<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\TypeConversion;

use Bond\Database\TypeConversion;

class GenericArray extends TypeConversion
{

    protected $isInt = false;

    public function __construct( $type, $isInt = false )
    {
        parent::__construct($type);
        $this->isInt = $isInt;
    }

    /**
     * Convert a postgres array to a php array. This has various limitation and shouldn't be considered 'safe'.
     * I gather there is no good way of doing this. See,  http://stackoverflow.com/questions/3068683/convert-postgresql-array-to-php-array for another idea.
     * When postgres json support lands in Postgres 9.2 (hopefully) we'll move over to that.
     *
     * @param string String representation of a postgres array. This is not the same as the array_to_string functionality.
     * @return array
     */
    public function __invoke($string)
    {

        if( $string === null ) {
            return null;
        }

        $string = trim( $string, '{}' );
        $output = str_getcsv( $string, ',', "'" );

        // convert 'NULL' to NULL
        foreach( $output as $key => $value ) {
            if( $value === 'NULL' ) {
                $output[$key] = null;
            } elseif( $this->isInt ) {
                $output[$key] = (int) $value;
            } else {
                $output[$key] = $value;
            }
        }

        return $output;

    }

}