<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class MissingOptionException extends \Exception
{
    public function __construct( array $missingOptions, $keysAsOptions = false )
    {
        if( $keysAsOptions ) {
            $missingOptions = array_flip( $missingOptions );
        }
        $options = [];
        foreach( $missingOptions as $option ) {
            $options[] = "`{$option}`";
        }
        parent::__construct(
            sprintf(
                "Missing options %s",
                implode( ',', $options )
            )
        );
    }
}