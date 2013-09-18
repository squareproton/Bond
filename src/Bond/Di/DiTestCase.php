<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Di;

use Bond\Di\Exception\NoServiceDefinedException;

abstract class DiTestCase extends \PHPUnit_Framework_Testcase
{

    /**
     * Utility method to get a ContainerFromAnnotations
     * @return Bond\Di\ContainerFromAnnotations
     */
    protected static function getContainerFromAnnotations()
    {
        return new ContainerFromAnnotations(get_called_class());
    }

    public function setup()
    {

        $diHelper = self::getContainerFromAnnotations();

        $container = $diHelper->getContainer();

        // check service namee
        if( !$serviceName = $this->getServiceName($diHelper) ) {
            // does this container have a service defined with this class name?
            // run up class heiracy
            $r = $diHelper->reflector;
            while( !$serviceName and $r and $r->getName() !== __CLASS__ ) {
                $_class = \Bond\get_unqualified_class($r->getName());
                if( $container->has($_class) ) {
                    $serviceName = $_class;
                }
                $r = $r->getParentClass();
            }
            if( !$serviceName ) {
                return $container; //throw new NoServiceDefinedException($reflector);
                throw new NoServiceDefinedException($reflector);
            }
        }

        $clone = $container->get($serviceName);

        foreach ($diHelper->reflector->getProperties() as $property) {
            if (
                $property->getDeclaringClass()->getName() !== \PHPUnit_Framework_TestCase::class and
                !$property->isStatic()
            ) {
                // We need a different 'getter' reflection object because we can then work
                // with objects of different classes.
                try {
                    $reflGetter = new \ReflectionProperty( $clone, $property->getName() );
                } catch ( \ReflectionException $e ) {
                    continue;
                }
                $reflGetter->setAccessible(true);
                $value = $reflGetter->getValue($clone);

                $property->setAccessible(true);
                $property->setValue($this, $value);
            }
        }

        return $container;

    }

    private function getServiceName( ContainerFromAnnotations $diHelper )
    {
        // get service name
        foreach( $diHelper->getClassAnnotations() as $annotation ) {
            // is this a service
            if( 0 === strpos($annotation, "service") ) {
                return explode(" ", $annotation)[1];
            }
        }
        return null;
    }

}