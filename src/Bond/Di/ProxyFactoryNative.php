<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class ProxyFactoryNative implements Factory
{
    private $container;
    private $name;

    function __construct($container, $name)
    {
        $this->container = $container;
        $this->name = $name;
    }

    private function isInject($arg)
    {
        return is_object($arg) and $arg instanceof Inject;
    }

    private function validateCreateArgs($args) {
        $definition = $this->container->getDefinition($this->name);

        $injectArgs = array_filter(
            $definition->getArguments(),
            function($arg) {
                return $this->isInject($arg);
            }
        );

        if (count($injectArgs) !== count($args)) {
            $n = count($injectArgs);
            $m = count($args);
            throw new \Exception("method create expecting $n arguments, $m given");
        }
    }

    public function create()
    {
        $createArguments = func_get_args();

        $this->validateCreateArgs($createArguments);
        $definition = clone ($this->container->getDefinition($this->name));
        $constructorArgs = []; // indexed
        foreach ($definition->getArguments() as $metaArgument) {
            $constructorArgs[] = $this->isInject($metaArgument)
                ? array_shift($createArguments)
                : $metaArgument;
        }
        $definition->setArguments($constructorArgs);
        return $this->resolve($definition);
    }

    // todo heavy optimization likely to be needed for this method
    private function resolve(Definition $definition)
    {
        if(!$this->container->isFrozen()) {
            throw new \LogicException("cannot use create method on factory without freezing/compiling container");
        }
        $subContainer = new ContainerBuilder();
        $subContainer->merge($this->container);
        $subContainer->setDefinition("__tmp__", $definition);
        $subContainer->compile();
        $obj = $subContainer->get("__tmp__");
        return $obj;
    }

}