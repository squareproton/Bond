<?php

namespace Bond\Pg\Converter;

abstract class CompositeParser {

    // const ESCAPE_CHARS = '\\"';
    // const CHARS_NEEDING_ESCAPE = '\"';
    // const FIELD_ENCLOSED_BY = '"';
    // const FIELD_SEPARATOR = ',';
    // const TRIM_LEADING = '()';

    protected function parseComposite($data)
    {

        $data = trim( $data, static::TRIM_LEADING );

        $chrArray = preg_split('//u', $data, -1, PREG_SPLIT_NO_EMPTY);

        $output = [];
        $fieldIndex = -1;

        while( list(,$chr) = each($chrArray) ) {

            $fieldIndex++;

            // straight into next record?
            if( static::FIELD_SEPARATOR === $chr ) {
                $output[$fieldIndex] = null;
                continue;

            // quoted field
            } elseif ( static::FIELD_ENCLOSED_BY === $chr ) {
                $output[$fieldIndex] = '';
                while( list(, $chr) = each($chrArray) ) {
                    // is escape char
                    if ( false !== strpos( static::ESCAPE_CHARS, $chr ) ) {
                        // each has already moved the internal array pointer forward
                        $nextChr = current($chrArray);
                        if( false !== $nextChr and false !== strpos( static::CHARS_NEEDING_ESCAPE, $nextChr ) ) {
                            $output[$fieldIndex] .= $nextChr;
                            // catch the array pointer up with the character we've just added
                            next($chrArray);
                            continue;
                        }
                    }
                    // is end quote character
                    if ( static::FIELD_ENCLOSED_BY === $chr ) {
                        // consume the next comma which is part of this field's termination
                        list(, $nextChr) = each($chrArray);
                        break;
                    // regular char: comsume.
                    } else {
                        $output[$fieldIndex] .= $chr;
                    }
                }

            // unquoted field
            } else {
                $output[$fieldIndex] = null;

                // don't need to check for quotes, commas or other exotics as postgres wraps fields containing these in double quotes
                while( static::FIELD_SEPARATOR !== $chr and null !== $chr ) {
                    $output[$fieldIndex] .= $chr;
                    list(, $chr) = each($chrArray);
                }
                // check for the magic value "NULL"
                if( 'NULL' === $output[$fieldIndex] ) {
                    $output[$fieldIndex] = null;
                }
            }

        }

        return $output;

    }

}