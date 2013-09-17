<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Repository;

use Bond\Entity\Base;

interface BaseInterface
{

    public function find( $key, $disableLateLoading );

    public function make();

    // multiton specific methods
    public function attach( Base $entity, &$restingPlace = null );

    public function detach( Base $entity, &$detachedFrom = null );

    public function isNew( Base $entity, $state = null );

    public function rekey( Base $entity );

}
