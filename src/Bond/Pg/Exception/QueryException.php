<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Exception;

class QueryException extends \Exception
{

    /**
     * SQL state code - http://www.postgresql.org/docs/8.4/static/errcodes-appendix.html
     * @var string
     */
    public $state;

    /**
     * The Sql which produced this error
     * @var string
     */
    public $sql;

    /**
     * Postgres' error message
     * @var string
     */
    public $error;

    /**
     * Sql state message.
     * @var string
     */
    public $stateMessage;

    /**
     * Standard constructor, blah, blah
     */
    public function __construct( $error, $state, $stateMessage, $sql )
    {
        $this->error = $error;
        $this->state = $state;
        $this->stateMessage = $stateMessage;
        $this->sql = $sql;
        $this->message = $error;
    }

}