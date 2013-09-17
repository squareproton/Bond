<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class ProxyFactory
 * @package Bond\Di
 * @deprecated use ProxyFactoryNative
 */
class ProxyFactory implements Factory
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

    private function isReference($arg)
    {
        return is_object($arg) and $arg instanceof Reference;
    }

    private function isDefinition($arg)
    {
        return is_object($arg) and $arg instanceof Definition;
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

        $definition = clone ($this->container->getDefinition($this->name));
        $this->validateCreateArgs($createArguments);

        $constructorArgs = []; // indexed
        foreach ($definition->getArguments() as $metaArgument) {
            $constructorArgs[] = $this->isInject($metaArgument)
                ? array_shift($createArguments)
                : $metaArgument;
        }
        $definition->setArguments($constructorArgs);

        $obj = $this->buildFromDefinition($definition);
        return $obj;
    }

    private function buildFromDefinition($definition) {

        $constructorArgs = [];
        foreach ($definition->getArguments() as $metaArgument) {
            $constructorArgs[] = $this->resolve($definition, $metaArgument);
        }

        $reflection = new \ReflectionClass($definition->getClass());
        $obj = $reflection->newInstanceArgs($constructorArgs);

        foreach ($definition->getMethodCalls() as $callSetting) {
            $name = $callSetting[0];
            $args = [];
            foreach ($callSetting[1] as $metaArgument) {
                $args[] = $this->resolve($definition, $metaArgument);
            }
            call_user_func_array([$obj, $name], $args);
        }

        foreach ($definition->getProperties() as $propertyName => $propertyValue) {
            $obj->$propertyName = $this->resolve($definition, $propertyValue);
        }

        return $obj;
    }

    private function resolve($definition, $metaArgument)
    {
        if($this->isInject($metaArgument)) {
            throw new InvalidInjectException($definition);
        } elseif($this->isReference($metaArgument)) {
            return $this->container->get((string)$metaArgument);
        } elseif($this->isDefinition($metaArgument)) {
            return $this->buildFromDefinition($metaArgument);
        } else {
            return $metaArgument;
        }

    }

}