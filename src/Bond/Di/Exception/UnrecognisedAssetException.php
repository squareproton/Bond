<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di\Exception;

class UnrecognisedAssetException extends \Exception
{
    public $asset;
    public function __construct( $asset )
    {
        $this->asset = $asset;
        $this->message = <<<TEXT
Asset `{$asset}` is unrecognised.
    1. If this is meant to be a file path ensure it is full qualified and starts with a / and it must exist
    2. If this is a class check the autoloader can see it and it my implement Bond/Di/Diconfigurable
TEXT
;
    }
}