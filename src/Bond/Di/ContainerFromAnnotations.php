<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Bond\Di\Exception\NoResourcesDefinedException;

use ReflectionClass;

use Bond\MagicGetter;

class ContainerFromAnnotations
{

    Use MagicGetter;

    private $reflector;

    public function __construct( $classOrObject )
    {
        $this->reflector = new ReflectionClass($classOrObject);
    }

    public function getContainer()
    {

        $container = new ContainerBuilder();
        $configurator = new Configurator($container);

        // check we've got some resources delcared
        if( !$resources = $this->getResources() ) {
            // is the class itself a resource?
            if( $this->reflector->hasMethod('__invoke') ) {
                $resources[] = $reflector->getName();
            }
            throw new NoResourcesDefinedException($reflector);
        }

        $configurator->load($resources);
        $container->compile();

        return $container;

    }

    /**
     * list of files to load as configuration.
     * should be set to empty list if none needed
     */
    private function getResources()
    {

        $resources = [];
        foreach( $this->getClassAnnotations() as $annotation ) {

            // is this a resource
            if( 0 === strpos($annotation, "resource") ) {
                $resources[] = explode(' ', $annotation)[1];
            }

        }

        return $resources;

    }

    public function getClassAnnotations( $goUpClassHierarchy = true )
    {

        $reflector = $this->reflector;

        $annotations = [];

        // run up class heiracy
        while( $reflector and $reflector->getName() !== __CLASS__ ) {

            $doc = $reflector->getDocComment();
            preg_match_all('#@(.*?)\n#s', $doc, $classAnnotations);

            // add each annotation to output
            foreach( $classAnnotations[1] as $annotation ) {

                $annotation = trim($annotation);

                // replace magic constants
                $annotation = preg_replace_callback(
                    '/(__[A-Z]+__)/U',
                    function( $matches ) use ($reflector) {
                        switch( $matches[0] ) {
                            case '__CLASS__':
                                return $reflector->getName();
                            case '__NAMESPACE__':
                                return $reflector->getNamespaceName();
                            case '__DIR__':
                                return dirname($reflector->getFileName());
                        }
                        return $matches[0];
                    },
                    $annotation
                );

                // is this a resource
                if( 0 === strpos($annotation, "resource") ) {

                    $resource = explode(' ', $annotation)[1];

                    // make relative paths in resources absolute
                    // this is done here because we know where the resource was defined
                    if( $this->endsWith( $resource, ".yml") and 0 !== strpos($resource, '/') ) {
                        $resource = dirname($reflector->getFileName()).'/'.$resource;
                    }

                    $annotation = "resource {$resource}";

                }

                $annotations[] = $annotation;

            }

            // visit parent?
            $reflector = $goUpClassHierarchy ? $reflector->getParentClass() : null;

        }
        return $annotations;
    }

    private function startsWith($haystack, $needle)
    {
        return $needle === "" || strpos($haystack, $needle) === 0;
    }

    private function endsWith($haystack, $needle)
    {
        return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
    }

}