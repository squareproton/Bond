<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Tests;

class NormalityProviderTest extends NormalityProvider
{

    public function testSomething()
    {
        $em = $this->entityManager;
        $repo = $em->getRepository('A1');
    }

}