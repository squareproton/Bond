<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

use Bond\Di\Exception\UnrecognisedAssetException;
use Bond\Di\Exception\ClassNotAConfigurableException;

class Configurator
{

    /**
     * @var Symfony\Component\DependencyInjection\ContainerBuilder
     */
    private $container;

    public function __construct (ContainerBuilder $container)
    {

        $this->container = $container;

        // register factory builder extension if it doesn't exist
        if( !$this->container->hasExtension (FactoryBuilderExtension::ALIAS) ) {
            $factoryBuilderExtension = new FactoryBuilderExtension();
            $this->container->registerExtension ($factoryBuilderExtension);
            $this->container->loadFromExtension($factoryBuilderExtension::ALIAS);
        }

    }

    /**
     * Compile and return the container
     */
    public function compile()
    {
        $this->container->compile();
        return $this->container;
    }

    /**
     * Load a collection of assets into the Di container.
     * Provides a whole load of syntatic sugar so you can basically pass this a combination of arrays, assets, multiple args, ... whatever
     * The actual work is done by loadHandler
     * @return mixed[]. Array of the individual assets loaded.
     */
    public function load()
    {
        $output = [];
        $args = func_get_args();
        foreach( $args as $arg ) {
            if( is_array($arg) ) {
                $output = array_merge(
                    call_user_func_array( [$this, 'load'], $arg ),
                    $output
                );
            } else {
                $this->loadHandler($arg);
                $output[] = $arg;
            }
        }
        return $output;
    }

    /**
     * Actually loads / configures a container from a single asset source
     * Takes either a
     *     Object which implements DiConfigurable
     *     String Fully qualified path name
     *     String Class name of the type the autoloader would understand
     * @param mixed
     */
    private function loadHandler( $asset )
    {
        // objects of class configurable
        if( is_callable($asset) ) {

            return call_user_func($asset, $this, $this->container);

        // this looks like a file name
        // because we're not taking relative paths look for 1st character being a string
        } elseif ( is_string($asset) and 0 === strpos($asset, '/') and file_exists($asset) ) {

            $yamlFileLoader = new YamlFileLoader(
                $this->container,
                new FileLocator(dirname($asset))
            );
            return $yamlFileLoader->load(basename($asset));

        } elseif ( is_string($asset) and class_exists( $asset ) /* and is_a( $asset, DiConfigurable::class ) */ ) {

            $invokedAsset = new $asset();
            if( !method_exists( $asset, '__invoke' ) ) {
                throw new ClassNotAConfigurableException($asset);
            }
            return call_user_func($invokedAsset, $this, $this->container);

        }

        throw new UnrecognisedAssetException($asset);
    }

    public function loadYamlFiles()
    {
        $reflection = new \ReflectionClass($this);
        $loader = new YamlFileLoader(
            $this->container,
            new FileLocator (dirname ($reflection->getFileName()))
        );
        array_map(
            function($f)use($loader){$loader->load($f);},
            $this->yamlFiles
        );
    }

    public function mergeContainer( Container $container )
    {
        $this->container->merge($container);
    }

    public function add(
        $name,
        $class,
        array $args = [],
        $scope = "prototype",
        $factory = false
    )
    {
        $definition = (new Definition($class, $args))->setScope($scope);
        $this->container->setDefinition(
            $name,
            $definition
        );

        if($factory === true) {
            $this->addFactory($name);
        } else if(is_string($factory)) {
            $this->addFactory($factory, $name);
        }

        return $definition;
    }

    /**
     * parameters: [factoryName] definitionName [factoryScope]
     * @throws \InvalidArgumentException
     */
    public function addFactory()
    {
        $args = func_get_args();

        if(0 === count($args) or count($args) > 3) {
            throw new \InvalidArgumentException("incorrect number parameters");
        }

        $definitionName = (1 === count($args)) ? $args[0] : $args[1];
        $factoryName = (1 === count($args)) ? ($args[0] . "Factory") : $args[0];
        $factoryScope = (count($args) < 3) ? "prototype" : $args[2];

        $config = [
            FactoryBuilderExtension::FACTORY_NAME => $factoryName,
            FactoryBuilderExtension::DEFINITION_NAME => $definitionName,
            FactoryBuilderExtension::FACTORY_SCOPE => $factoryScope
        ];

        $this->container->loadFromExtension(
            FactoryBuilderExtension::ALIAS,
            [$config]
        );

        return $this;
    }

}