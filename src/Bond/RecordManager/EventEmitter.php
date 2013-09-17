<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\RecordManager;

use Bond\Event\EmitterMinimal;

class EventEmitter
{
    use EmitterMinimal;

    const TRANSACTION_START = '__TRANSACTION_START';
    const TRANSACTION_COMMIT = '__TRANSACTION_COMMIT';
    const TRANSACTION_ROLLBACK = '__TRANSACTION_ROLLBACK';
}