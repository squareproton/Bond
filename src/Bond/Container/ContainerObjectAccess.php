<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container;

use Bond\Container;
use Bond\Container\PropertyMapperObjectAccess;

class ContainerObjectAccess extends Container
{
    public function __construct()
    {
        $this->setPropertyMapper(PropertyMapperObjectAccess::class);
        call_user_func_array(
            'parent::__construct',
            func_get_args()
        );
    }
}