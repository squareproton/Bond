<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Tests;

use Bond\Repository;

class E
{
}

class R extends Repository
{
}

class EntityManagerTest extends EntityManagerProvider
{

    public function testSomething()
    {

        $em = $this->entityManager;
        $em->register( E::class, R::class );

        $this->assertTrue( $em->getRepository('E') instanceof R );

    }

}