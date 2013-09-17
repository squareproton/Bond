<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity\Types;

interface FileInterface
{

    public function data();

    public function stream();

    public function export( $destination, $overwriteIfExists );

    public function md5();

}