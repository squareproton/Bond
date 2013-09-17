<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Tests;

use Bond\Pg\Tests\PgProvider;

/**
 * @service testEntityManager
 */
class EntityManagerProvider extends PgProvider
{
    public $entityManager;
}