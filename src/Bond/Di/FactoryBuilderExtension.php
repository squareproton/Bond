<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 *
 * for yml configuration use syntax:
 * factory_builder:
 *   - factory: factory_name
 *     definition: definition_name
 *     scope: scope_of_factory
 *   - ...
 *
 */
class FactoryBuilderExtension implements ExtensionInterface
{
    const ALIAS = "factorybuilder";
    const FACTORY_NAME = "factory";
    const DEFINITION_NAME = "definition";
    const FACTORY_SCOPE = "scope";

    public function __construct ()
    {
        $this->factoryDefinitions = [];
    }

    public function load(array $configs, ContainerBuilder $container)
    {

        $factoryDefinitions = [];
        foreach ($configs as $configTop) {
            foreach ($configTop as $config) {
                assert(array_key_exists(self::FACTORY_NAME, $config));
                assert(array_key_exists(self::DEFINITION_NAME, $config));
                assert(array_key_exists(self::FACTORY_SCOPE, $config));
                $factoryDefinitions[] = $config;
            }
        }

        foreach ($factoryDefinitions as $definition) {

            if(
                !$container->hasDefinition($config[self::DEFINITION_NAME]) and
                0 === count(array_filter(
                    $factoryDefinitions, 
                    function($f)use($definition){
                        return $f[self::FACTORY_NAME] === $definition[self::FACTORY_NAME];
                    }
                ))
            ) {
                throw new \Exception(sprintf(
                    "no definition '%s' defined in container. existing definitions: " . implode(", ", array_keys($container->getDefinitions())),
                    $config[self::DEFINITION_NAME]
                ));
            }

            $factoryName = $definition[self::FACTORY_NAME];
            $definitionName = $definition[self::DEFINITION_NAME];
            $factoryScope = $definition[self::FACTORY_SCOPE];

            $container->setDefinition(
                $factoryName,
                new Definition(ProxyFactoryNative::class, [
                    new Reference("service_container"),
                    $definitionName
                ])
            )->setScope($factoryScope);

        }
    }

    public function getXsdValidationBasePath()
    {
        return false;
    }

    /**
     * Returns the namespace to be used for this extension (XML namespace).
     *
     * @return string The XML namespace
     */
    public function getNamespace()
    {
        return 'http://example.org/schema/dic/'.$this->getAlias();
    }

    public function getAlias()
    {
        return self::ALIAS;
    }

}