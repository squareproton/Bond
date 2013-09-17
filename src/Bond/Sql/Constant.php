<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Sql;

use Bond\Sql;
use Bond\Sql\Exception\UnknownConstantException;
use Bond\Sql\SqlPassthroughInterface;

class Constant extends Sql implements SqlPassthroughInterface
{

    /**
     * Allowed SQL constants
     */
    protected static $constants = array(
        'DEFAULT',
    );

    /**
     *
     * @param <type> $value
     */
    public function __construct( $value )
    {
        if( !in_array( $value, self::$constants ) ) {
            throw new UnknownConstantException( "Constant `{$value}` isn't in our whitelist. The whitelist might not be upto date." );
        }
        parent::__construct( $value );
    }

}