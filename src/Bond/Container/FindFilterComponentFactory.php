<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Container;

use Bond\Container;
use Bond\Container\PropertyMapperObjectAccess;
use Bond\Container\FindFilterComponentMulti;
use Bond\Exception\NeedsOverloadingException;
use Bond\MagicGetter;
use ReflectionClass;

class FindFilterComponentFactory
{

    use MagicGetter;

    /**
     * Class contants
     */
    const FIND_ALL = '__FIND_ALL';
    const FIND_ONE = '__FIND_ONE';

    /**
     * Regex to split operations
     * @var string
     */
    protected static $regexDefault = null;

    /**
     * Allowed operators
     * @var array
     */
    protected static $operations = array(
        'And' => 'AND',
        'Or' => 'OR',
    );

    /**
     * The namespace where we expect the FindFilterComponent folder located
     */
    protected $namespace;

    /**
     * The reflection object of the property mapper class
     */
    protected $reflPropertyMappers = [];

    /**
     * Standard __construct
     * @param mixed null|object|string|array
     */
    public function __construct( $propertyMapperClass = null, $namespace = null )
    {

        if( !$namespace ) {
            $namespace = \Bond\get_namespace($this);
        }
        $this->namespace = $namespace;

        // array-ify propertyMapperClasses
        if( !$propertyMapperClass  ) {
            $propertyMapperClass = [ PropertyMapperObjectAccess::class ];
        } elseif( !is_array( $propertyMapperClass ) ) {
            $propertyMapperClass = [ $propertyMapperClass ];
        }

        foreach( $propertyMapperClass as $mapper ) {
            $this->reflPropertyMappers[] = new \ReflectionClass( $mapper );
        }

    }

    /**
     * Init the correct type of FindFilterComponent based what is passed
     * @param mixed $value
     * @return ReflectionClass of the FindFilterComponent
     */
    public function getReflClassFromComparisonValue( $value )
    {

        if( is_array($value) ) {
            $class = $this->namespace . '\\FindFilterComponent\\PropertyAccessArrayEquality';
        } elseif ( is_object($value) and $value instanceof Container ) {
            $class = $this->namespace . '\\FindFilterComponent\\PropertyAccessContainerEquality';
        } else {
            $class = $this->namespace . '\\FindFilterComponent\\PropertyAccessScalarEquality';
        }
        return new ReflectionClass($class);
    }

    /**
     * Return a new instance of a FindFilterComponent
     * @return Bond\Container\FindFilterComponent
     */
    private function getHelper( \ReflectionClass $propertyMapper, $name, $operation, $value )
    {
        $mapper = $propertyMapper->newInstance($name);
        $instance = $this->getReflClassFromComparisonValue($value)
            ->newInstance($mapper, $operation, $value);
        return $instance;
    }

    /**
     * Return a new instance of a FindFilterComponent but Multi-PropertyMapperAware
     * @return Bond\Container\FindFilterComponent
     */
    public function get( $name, $operation, $value)
    {

        $filterComponents = [];
        foreach( $this->reflPropertyMappers as $propertyMapper ) {
            $filterComponents[] = $this->getHelper( $propertyMapper, $name, $operation, $value );
        }

        // not a multi - just return the lone FindFilterComponent
        if( 1 === count($filterComponents) ) {
            return $filterComponents[0];
        }

        // is a multi - instantiate all the FindFilterComponents and build a FindFilterComponentMulti
        return new FindFilterComponentMulti(
            $filterComponents,
            $operation,
            $value
        );

    }

    /**
     * Generate a array of FindFilter components based off a string. Used by $container->__call()
     * @param string $string
     * @param array $arguments
     * @return array(FindFilterComponent)
     */
    public function initComponentsByString( $string, array $arguments )
    {

        // find operations
        if( preg_match_all( $this->getRegex(), $string, $matches ) ) {

            $operations = array( null );
            foreach( $matches[0] as $match ) {
                $operations[] = self::$operations[$match];
            }

            /*
            if( count( array_unique( $operations ) ) > 1 ) {
                throw new \Exception("don't support ambigous expressions");
            }
             */

            $elements = array_combine(
                array_map( 'lcfirst', preg_split( $this->getRegex(), $string ) ),
                $operations
            );

        } else {
            $elements[lcfirst($string)] = null;
        }

        // check we've been passed the right number of arguments
        if( count( $elements) !== count( $arguments ) ) {
            throw new \LogicException(
                sprintf(
                    "FindFilterComponent method `%s` has been split into %d components. Number of arguments passed %d. These should be the same.",
                    $string,
                    count( $elements ),
                    count( $arguments )
                )
            );
        }

        $output = array();
        foreach( $elements as $name => $operation ) {
            $output[$name] = $this->get(
                $name,
                $operation,
                array_shift( $arguments )
            );
        }

        return $output;

    }

    /**
     * Get the regular expression for identifying delimiters and tags within
     * @return regex
     */
    protected function getRegex()
    {

        if( !self::$regexDefault ) {
            self::$regexDefault = implode(
                '|',
                array_map(
                    function($value){
                        return "(?<=[a-z0-9]){$value}(?=[A-Z])";
                    },
                    array_flip( self::$operations )
                )
            );
            self::$regexDefault = "/".self::$regexDefault."/";
        }

        return self::$regexDefault;

    }

}