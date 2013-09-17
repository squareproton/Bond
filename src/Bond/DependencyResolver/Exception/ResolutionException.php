<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\DependencyResolver\Exception;

class ResolutionException extends \Exception
{

    public $id;
    public $previousException;

    public function __construct($id, \Exception $e)
    {
        $this->id = $id;
        $this->previousException = $e;

        $message = sprintf(
            <<<EXCEPTION
Exception thrown resolving dependency `%s` exception

%s

EXCEPTION
,
            $id,
            (string)$e
        );

        $this->message = $message;

    }

}