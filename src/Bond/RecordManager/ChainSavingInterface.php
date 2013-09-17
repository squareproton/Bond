<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager;

use Entity;

// Used by the record manager to detect chainsaveable objects
interface ChainSavingInterface
{
    public function chainSaving( array &$tasks, $name );
}