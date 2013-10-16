<?php

namespace Bond\Pg\Converter;

class PgBytea implements ConverterInterface
{

    // Turns out this isn't needed.
    // I assume Pomm has a reason for this function instead of relying on pg_unescape_bytea but I've no idea why?

    // /**
    //  * Copied from https://github.com/chanmix51/Pomm/blob/1.1/Pomm/Converter/PgBytea.php
    //  *
    //  * Pomm\Converter\PgBytea - Bytea converter
    //  *
    //  * @package Pomm
    //  * @version $id$
    //  * @copyright 2011 Grégoire HUBERT
    //  * @author Grégoire HUBERT <hubert.greg@gmail.com>
    //  * @license X11 {@link http://opensource.org/licenses/mit-license.php}
    //  */
    // private function unescByteA($data)
    // {
    //     $search = array('\\000', '\\\'', '\\');
    //     $replace = array(chr(0), chr(39), chr(92));
    //     $data = str_replace($search, $replace, $data);
    //     $data = preg_replace_callback('/\\\\([0-9]{3})/', function($byte) { return chr((int) base_convert((int) $byte[1], 8, 10)); }, $data);
    //     return $data;
    // }

    public function __invoke($data, $type = null)
    {
        if( null === $data ) {
            return null;
        }
        return pg_unescape_bytea($data);
        // if (is_resource($data)) {
        //     return stripcslashes(@stream_get_contents($data));
        // }
        // return $this->unescByteA(stripcslashes($data));
    }

}