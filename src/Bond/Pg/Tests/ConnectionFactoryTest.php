<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Tests;

use Bond\Pg\ConnectionFactory;

class ConnectionFactoryTest extends PgProvider
{

    public function getInstances( ConnectionFactory $factory )
    {
        $reflResourceInstances = (new \ReflectionClass($factory))->getProperty('instances');
        $reflResourceInstances->setAccessible(true);
        return $reflResourceInstances->getValue($factory);
    }

    public function testUndefinedConnection()
    {

        $this->setExpectedException('Bond\Database\Exception\UnknownNamedConnectionException');
        $test = $this->connectionFactory->get( 'THIS clEARly isn"t a DB connecTION sTriINg');
    }

    public function testMultitonBehaviour()
    {

        $this->assertSame(
            $this->connectionFactory->get('R')->resource,
            $this->connectionFactory->get('R')->resource
        );

        $this->assertSame(
            count($this->getInstances($this->connectionFactory)),
            1
        );

    }

}