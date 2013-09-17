<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database;

use Bond\Database\TypeConversionInterface;
use Bond\MagicGetter;

abstract class TypeConversion implements TypeConversionInterface
{

    use MagicGetter;

    protected $type;

    /**
     * Standard getter
     *
     * @param String representation of a type
     */
    public function __construct( $type )
    {
        $this->type = $type;
    }

}