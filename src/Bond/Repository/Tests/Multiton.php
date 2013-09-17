<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Repository\Tests;

/**
 * Unittest Repository
 * @author pete
 */
class Multiton extends \Bond\Repository\Multiton
{

    protected $instancesMaxAllowed = 100;

    /**
     * When passed a key (think Entity\Base->$lateLoadKey) return a array suitable for passing to a entities constructor
     * @param <type> $key
     * @return <type>
     */
    public function data( $key )
    {
        // used to simulate a bad record
        if( $key === 'notfound' ) {
            return array();
        }

        return array(
            'id' => $key,
            'name' => "name-{$key}",
        );
    }

}