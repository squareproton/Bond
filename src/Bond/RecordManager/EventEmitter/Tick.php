<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager\EventEmitter;

use Bond\RecordManager;
use Bond\RecordManager\EventEmitter;

use Bond\Pg\Result;
use Bond\Sql\Raw;
use Bond\Sql\Query;
use Bond\MagicGetter;

class Tick extends EventEmitter
{

    use MagicGetter;

    private $isTickSupported;

    public function __construct()
    {
        $this->on( self::TRANSACTION_START, [$this, 'onTransactionStart'] );
    }

    public function onTransactionStart( $e, RecordManager $recordManager, $transactionName )
    {

        // isTickSupported
        if( null === $this->isTickSupported ) {
            $query = new Raw( "SELECT count(*) = 1 FROM pg_namespace INNER JOIN pg_proc ON pg_proc.pronamespace = pg_namespace.oid WHERE pg_namespace.nspname = 'logs'::name AND pg_proc.proname = 'tick'::name;");
            $this->isTickSupported = $recordManager->db->query( $query )->fetch( Result::FETCH_SINGLE | Result::TYPE_DETECT );
        }

        if( $this->isTickSupported ) {
            $tick = new Query( "SELECT tick( %transactionName:text|null%::text );" );
            $tick->transactionName = $transactionName;
            $recordManager->db->query( $tick );
        }

    }

}