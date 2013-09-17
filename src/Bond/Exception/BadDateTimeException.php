<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Exception;

class BadDateTimeException extends \InvalidArgumentException
{
    public $var;
    public function __construct( $supposedDateTime = null, \Exception $previous = null )
    {

        $this->var = $supposedDateTime;

        parent::__construct(
            sprintf(
                'Invalid DateTime "%s"',
                $supposedDateTime
            ),
            null,
            $previous
        );

    }
}