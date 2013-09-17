<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\TypeConversion;

use Bond\Database\TypeConversion;

class Bool extends TypeConversion
{

    public function __invoke($input)
    {
        if (null === $input) {
            return null;
        }
        return $input === 't';
    }

}