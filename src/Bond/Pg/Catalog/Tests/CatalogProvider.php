<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Pg\Catalog\Tests;

use Bond\Di\DiTestCase;
use Bond\DI\Configurator;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @resource ../../Tests/Di/ConnectionFactoryConfigurable.yml
 * @service testPg
 */
class CatalogProvider extends DiTestCase
{

    public $connectionFactory;

    // don't maintain a tons of open database connections
    // close any that are still up and running
    public static function tearDownAfterClass()
    {
        $reflResourceInstances = (new \ReflectionClass('Bond\Pg\Resource'))->getProperty('instances');
        $reflResourceInstances->setAccessible(true);
        foreach( $reflResourceInstances->getValue() as $resource ) {
            $resource->terminate();
        }
    }

}