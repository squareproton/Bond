<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Database\Exception;

use Bond\Pg\Resource;

class DatabaseAlreadyExistsException extends \Exception
{
    public $dbname;
    public function __construct( $dbname )
    {
        $this->dbname = $dbname;
        $this->message = sprintf(
            "Database {$this->dbname} already exists. It may no longer be used."
        );
    }
}