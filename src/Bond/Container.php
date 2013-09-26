<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

// use Bond\Container\Exception\EntityPropertyNotAEntityException;
use Bond\Container\Exception\IncompatibleContainerException;
use Bond\Container\Exception\IncompatibleQtyException;
use Bond\Container\Exception\UnknownFilterOperatorException;
use Bond\Container\Exception\UnexpectedPropertyException;

use Bond\Container\ContainerableInterface;
use Bond\Container\FindFilterComponentFactory;
use Bond\Container\PropertyMapperObjectAccess;

use Bond\Sql\SqlCollectionInterface;
use Bond\Sql\QuoteInterface;

use Bond\Exception\BadPropertyException;
use Bond\Exception\BadTypeException;
use Bond\Exception\UnknownPropertyForMagicGetterException;

use ReflectionClass;

/**
 * Slightly pimp container for objects
 * Supports finding, sorting, iterating and comparing collections of
 */
class Container implements \Iterator, \ArrayAccess, \Countable, SqlCollectionInterface
{

    /**
     * Array which this object wraps
     * @var array
     */
    protected $collection = array();

    /**
     * The class of a container. Once set a container can only be used to store objects of the correct type.
     * @var string. The fully qualified classname of a Entity.
     */
    protected $class;

    /**
     * Some functions require the container to access it's containing properties
     * @var Bond\Container\PropertyMapperInterface
     */
    protected $propertyMapper;

    /**
     * Class constructor.
     *
     * @param mixed $collection[, $property]
     * @return Container
     */
    public function __construct()
    {
        $args = func_get_args();
        if( $args ) {
            call_user_func_array( [$this, 'add'], $args );
        }
    }

    /**
     * Debugging for a container
     * @return string
     */
    public function __toString()
    {
        $castToString = function ($o) { return (string) $o; };
        return sprintf(
            "%s (%s) / %d\n[%s]\n",
            get_called_class(),
            $this->classGet(),
            count( $this ),
            $this->implode( ',', $castToString )
        );
    }

    /**
     * Set property mapper
     * @param string Class of Bond\Container\PropertyMapperInterface
     * @return Bond\Container;
     */
    public function setPropertyMapper($propertyMapper = PropertyMapperObjectAccess::class )
    {
        $this->propertyMapper = $propertyMapper;
        return $this;
    }

    /**
     * Get a propertyMapper
     * @param string Property
     * @return Bond\Container\PropertyMapperInterface
     *
     */
    public function getPropertyMapper($property)
    {
        if( !$this->propertyMapper ) {
            throw new NoPropertyMapperSet();
        }
        $refl = new ReflectionClass($this->propertyMapper);
        return $refl->newInstance($property);
    }

    /**
     * Standard magical getter
     * @return mixed
     */
    public function __get( $key )
    {
        switch( $key ) {
            case 'class':
                return $this->classGet();
            case 'collection':
                return $this->collection;
            case 'propertyMapper':
                return $this->propertyMapper;
        }
        throw new UnknownPropertyForMagicGetterException( $this, $key );
    }

    /**
     *
     * @param Copy a object
     * @return Container copy a
     */
    public function copy( $deep = false )
    {
        if( $deep ) {
            $output = $this->newEmptyContainer();
            foreach( $this->collection as $entity ) {
                if( method_exists($entity, 'copy') ) {
                    $copy = $entity->copy( true );
                } else {
                    $copy = clone $entity;
                }
                $output->collection[spl_object_hash($copy)] = $copy;
            }
            $output->class = $this->class;
        } else {
            $output = $this->newEmptyContainer();
            $output->collection = $this->collection;
            $output->propertyMapper = $this->propertyMapper;
            $output->class = $this->classGet();
        }
        return $output;
    }

    /**
     * Remove entities from collection
     * @param ContainerableInterface|Container|ContainerableInterface[]
     * @return int Number removed
     */
    public function remove()
    {

        $removed = 0;
        foreach( func_get_args() as $arg ){

            // is this a container?
            if( $arg instanceof Container ) {

                $originalCount = $this->count();
                $this->collection = array_diff_key( $this->collection, $arg->collection );
                $removed += ( $originalCount - $this->count() );

            } else {

                // use array search
                $search = $this->search( $arg, true );

                foreach( $search as $value ){
                    if( $value !== false ){
                        $removed++;
                        $this->offsetUnset( $value );
                    }
                }

            }

        }
        $this->rewind();
        return $removed;

    }

    /**
     * Filter entities in a container based on a callback.
     * Remove entities when filter callback returns false.
     * @param Closure
     * @return $this;
     */
    public function filter( $filterCallback )
    {
        $this->collection = array_filter(
            $this->collection,
            $filterCallback
        );
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoting )
    {

        $output = $this;

        // IN clauses require at least one value.
        // If this container is empty return '(null)'. This wont match anything;
        if( !$output->count() ) {
            return '(null)';
        }

        $values = [];
        foreach( $output->collection as $obj ) {
            $values[] = $obj->parse( $quoting );
        }
        return "(".implode(',', $values).")";
    }

    /**
     * Any. Returns true iff for any entity in the container the callback( $entity) returns true
     * @param callback
     * @return bool
     */
    public function any( callable $callback )
    {
        foreach( $this->collection as $entity ) {
            if( call_user_func( $callback, $entity) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Every. Returns true iff forevery entity in the container the callback( $entity) returns true
     * @param callback
     * @return bool
     */
    public function every( callable $callback, $passOnEmpty = true )
    {
        foreach( $this->collection as $entity ) {
            if( !call_user_func( $callback, $entity) ) {
                return false;
            }
        }
        return $this->collection ? true :  (bool) $passOnEmpty;
    }

    /**
     * Each. Execute a callback against every member of the container.
     * @param callback. Something acceptable for array walk
     * @return this
     */
    public function each( callable $callback )
    {
        array_walk( $this->collection, $callback );
        return $this;
    }

    /**
     * Map. Array map on the container
     * @param callback. Something acceptable for array_map
     * @return array
     */
    public function map( callable $callback )
    {
        return array_map( $callback, $this->collection );
    }

    /**
     * MapCombine. Like array map but takes a callback for the array keys
     * @param callback. Specifies the keys of the return array
     * @param callback. Specifies the values of the return array
     * @return array
     */
    public function mapCombine( $keys, $values )
    {
        return array_combine(
            array_map( $keys, $this->collection ),
            array_map( $values, $this->collection )
        );
    }

    /**
     * Sort a container by a user defined comparison function. See, http://www.php.net/manual/en/function.uasort.php
     * @param Closure $callback
     */
    public function sort( callable $callback )
    {
        uasort( $this->collection, $callback );
        reset( $this->collection );
        return $this;
    }

    /**
     * Pluck a property from the collection
     * @param string Property
     * @return mixed Bond\Container | mixed[]
     */
    public function pluck( $property, $ifEmptyDefaultToContainer = false )
    {

        $mapper = $this->getPropertyMapper($property);
        $isAllObjects = true;

        $output = [];
        foreach( $this->collection as $obj ) {
            $propertyValue = $mapper->get($obj);
            $isAllObjects = $isAllObjects and is_object($propertyValue);
            $output[] = $propertyValue;
        }
        if( $output ) {
            if( $isAllObjects ) {
                try {
                    return new Container($output);
                } catch ( \Exception $e ) {
                }
            }
        } elseif( $ifEmptyDefaultToContainer) {
            return new Container();
        }
        return $output;

    }

    /**
     * Return a user defined sort comparison function that is property mapper aware.
     * @param string Property to sort on.
     * @param const SORT_DESC | SORT_ASC Sort direction
     * @return callable
     */
    private function generateSortByPropertyClosure( $property, $direction = SORT_ASC )
    {

        if( $direction === SORT_DESC ) {
            $aLTb = 1;
            $aGTb = -1;
        } else {
            $aLTb = -1;
            $aGTb = 1;
        }

        $mapper = $this->getPropertyMapper($property);

        return function ( $a, $b ) use ( $mapper, $aLTb, $aGTb ) {
            $propertyA = $mapper->get($a);
            $propertyB = $mapper->get($b);
            if( $propertyA === $propertyB ) {
                return 0;
            }
            return ( $propertyA < $propertyB ) ? $aLTb : $aGTb;
        };

    }

    /**
     * Returns a callable to access a object property
     * @param string $property
     * @return callable
     */
    private function generateGetPropertyClosure( $property )
    {
        $mapper = $this->getPropertyMapper($property);
        return function( $obj ) use ( $mapper ) {
            return $mapper->get($obj);
        };
    }

    /**
     * Sort container by property
     * @param property $property
     * @param SORT_ASC|SORT_DESC $direction
     * @return Container
     */
    public function sortByProperty( $property, $direction = null )
    {
        $this->sort( $this->generateSortByPropertyClosure( $property, $direction ) );
        return $this;
    }

    /**
     * Add entitie(s) to this collection.
     *
     * @param ContainerableInterface|ContainerableInterface[]|Container
     * @return Container
     */
    public function add()
    {

        foreach( func_get_args() as $obj ){

            if( is_object($obj) ) {

                if( $obj instanceof Container ) {

                    if( !$this->checkEntityContainer( $obj, $error ) ) {
                        throw new IncompatibleContainerException($error);
                    }

                    $this->collection = $this->collection + $obj->collection;

                    // if the passed container has a class and we don't, update ours
                    if( isset( $obj->class ) and !isset( $this->class ) ) {
                        $this->class = $obj->class;
                    }

                } elseif( $this->checkEntity( $obj ) ) {

                    $this->collection[spl_object_hash($obj)] = $obj;

                }

            } elseif( is_array( $obj ) ) {

                if( !$this->checkEntityArray( $obj, $error ) ) {
                    throw new IncompatibleContainerException($error);
                }

                foreach ( $obj as $key => $_obj ) {
                    $this->collection[spl_object_hash($_obj)] = $_obj;
                }

            } elseif( is_null( $obj ) ) {

                continue;

            } else {

                throw new BadTypeException( $obj, "Not suitable to go into container" );;

            }

        }

        $this->rewind();

        return $this;
    }

    /**
     * Check an entity implments ContainerInterface.
     * It should be the same type of entities already in collection.
     *
     * @param mixed $obj
     * @return bool
     */
    private function checkEntity( $obj )
    {
        if( $class = $this->classGet() and !( $obj instanceof $class ) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Obj %s is not compatible with Container of class %s',
                    get_class( $obj ),
                    $class
                )
            );
        }
        return true;
    }

    /**
     * Check if a array of Entity's is compatible with this container
     * @param array $entityArray
     * @return bool
     */
    private function checkEntityArray( array $entityArray, &$error = null )
    {
        // get class
        if( $existing = $this->classGet() ) {
            foreach( $entityArray as $value ) {
                if( !( $value instanceof $existing ) ) {
                    $error = "Can't add entity of class `".get_class( $value )."` to container of class `{$existing}`.";
                    return false;
                }
            }
        }
        $error = '';
        return true;
    }

    /**
     * Check the Container is compatible with our Entity
     * @param Container $container
     * @param string $error
     * @return bool
     *
     */
    private function checkEntityContainer( Container $container, &$error = null )
    {
        $error = '';
        // get class'
        if( ( $existing = $this->classGet() ) and ( $new = $container->classGet() ) ) {
            if( $existing === $new ) {
                return true;
            // PHP's instanceof doesn't work with the first argument as a string
            } elseif( is_subclass_of( $new, $existing ) ) {
                return true;
            }
            $error = "Can't add container of class `{$new}` to `{$existing}`";
            return false;
        }
        return true;
    }

    /**
     * Return the first entity in this collection without messing with
     * the internal state pointer (or otherwise)
     * @return ContainerableInterface|null
     */
    public function peak()
    {
        $keys = array_keys( $this->collection );
        if( $keys ) {
            return $this->collection[array_shift($keys)];
        }
        return null;
    }

    /**
     * Does this container contain the following entities
     * @param ContainerableInterface|Container|ContainerableInterface[]
     * @return boolean
     */
    public function contains()
    {

        $output = true;
        $args = func_get_args();

        // support a arbritary number objects against which to test
        while( list(,$value) = each($args) and $output ) {

            // is entity
            if( $value instanceof Container ) {

                $numChecking = count( $value );
                $numContained = count( array_intersect_key( $value->collection, $this->collection ) );
                $testResult = $numChecking === $numContained;

            } elseif ( is_object($value) ) {

                $testResult = false !== array_search( $value, $this->collection, true );

            // is null or null like. See, http://en.wikipedia.org/wiki/Empty_set. All containers contain the empty container
            } elseif ( is_null( $value ) or $value === array() ){

                $testResult = true;

            // is a array or container
            } elseif ( is_array( $value ) ) {

                $testResult = true;
                while( list(,$entity) = each( $value ) and $testResult ) {
                    $testResult = ( $this->search( $entity ) !== false or !$entity );
                }

            // something else
            } else {
                throw new BadTypeException( $value, 'Container|array|obj' );
            }

            $output = ( $output and $testResult );

        }

        return $output;

    }

    /**
     * Empty a container but keep all other properties
     * @return Container
     */
    public function truncate()
    {
        $this->collection = array();
        return $this;
    }

    /**
     * Returns a container containing all of the entities which are present in $this and every argument
     * @param Container
     * @return Container
     */
    public function intersect()
    {
        return $this->arrayHelperMethod( 'array_intersect_key', func_get_args() );
    }

    /**
     * Returns a container containing all of the entities which are __not__ present in any of the arguments
     * @param Container
     * @return Container
     */
    public function diff()
    {
        return $this->arrayHelperMethod( 'array_diff_key', func_get_args() );
    }

    /**
     * Validation and execution helper method for intersect and diff.
     *
     * @param $fn Callback like object
     * @return Container
     */
    private function arrayHelperMethod( $fn, array $args )
    {

        $class = $this->classGet();
        $fnArguments = array( $this->collection );

        foreach( $args as $container ) {

            // validate the arguments
            if( !( $container instanceof $this ) ) {
                throw new \InvalidArgumentException("You can only `{$fn}` containers.");
            }
            $containerClass = $container->classGet();
            if( isset($class) and isset( $containerClass ) and ( $class !== $containerClass ) ) {
                throw new IncompatibleContainerException("Container -> `{$containerClass}` not compatible with `{$class}`");
            }

            $fnArguments[] = $container->collection;

        }

        $output = $this->newEmptyContainer(
            call_user_func_array(
                $fn,
                $fnArguments
            )
        );
        return $output;

    }

    /**
     * Are the contents of a container identical to another container.
     * This a === b if a contains b and count(a) = count(b);
     * @param Container $container
     * @param bool Is ordering significant. Is the order of elements in a container significant.
     * @return bool
     */
    public function isSameAs( Container $container, $orderingIsSignificant = false )
    {
        $comparingClass = $container->classGet();
        $thisClass = $this->classGet();

        if( $comparingClass and $thisClass and $thisClass !== $comparingClass ) {
            return false;
        }

        if( $orderingIsSignificant ) {
            return array_keys( $this->collection ) === array_keys( $container->collection );
        } else {
            return $this->contains( $container ) and count( $container ) === count( $this );
        }
    }

    /**
     * Is this container empty?
     * @return bool
     */
    public function isEmpty()
    {
        return count( $this->collection ) === 0;
    }

    /**
     * Find the keys for the following entit(ies)
     *
     * @param ContainerableInterface[]|Container|ContainerableInterface
     * @param ...
     * @return boolean|array
     */
    public function search( $value, $alwaysOutputAsArray = false )
    {

        if( $value instanceof Container ) {

            $output = array();
            foreach( array_keys( $value->collection ) as $key ) {
                $output[$key] = isset( $this->collection[$key] ) ? $key : false;
            }
            return $output;

        } elseif ( is_object($value) ) {

            $hash = spl_object_hash( $value );
            $search = isset( $this->collection[$hash] ) ? $hash : false;
//            $search = \array_search( $value, $this->collection, true );
            return $alwaysOutputAsArray ? array( $search ) : $search ;

        } elseif( is_array( $value ) ) {

            $output = array();
            foreach( $value as $key => $entity ) {
                $output[$key] = $this->search( $entity );
            }
            return $output;

        }

        return $alwaysOutputAsArray ? array( false ) : false ;

    }

    /**
     * Return a random number of entities from this collection.
     * If n = 1 you will recieve a entity otherwise you will get a container
     * @param int
     * @param bool Force a container to always be returned
     * @param bool Remove the items from the container as they are returned
     * @return ContainerableInterface|Container
     */
    public function randomGet( $n = null, $returnContainer = false, $removeFromContainer = false )
    {
        $n = $n === null ? 1 : (int) $n;

        // anything to give?
        if( 0 === $containerSize = $this->count() ) {
            if( $returnContainer or $n > 1 ) {
                return $this->newEmptyContainer();
            }
            return null;
        }

        // single entity
        if( $n === 1 ) {

            $key = array_rand( $this->collection );
            $entity = $this->collection[$key];

            if( $removeFromContainer ) {
                unset( $this->collection[$key] );
            }

            return $returnContainer
                ? $this->newEmptyContainer( $entity )
                : $entity
                ;

        }

        $output = $this->newEmptyContainer();

        // to protected against the peculiar, $n = 0 still returns one element
        if( $n > 1 ) {
            // asking for more than we've got?
            if( $n <= $containerSize ) {
                $keys = array_flip( array_rand( $this->collection, $n ) );
                $output->add( array_intersect_key( $this->collection, $keys ) );
                if( $removeFromContainer ) {
                    $this->collection = array_diff_key( $this->collection, $keys );
                }
            } else {
                $output->add( $this );
                if( $removeFromContainer ) {
                    $this->truncate();
                }
            }
        }
        return $output;
    }

    /**
     * Warning! Here be the ->findBy() magic
     * @return Container
     */
    public function __call( $method, $arguments )
    {

        // find by
        if( substr( $method, 0, 6 ) === 'findBy' ) {

            $filterFactory = new FindFilterComponentFactory( $this->propertyMapper );

            return $this->findByFilterComponents(
                $filterFactory::FIND_ALL,
                $filterFactory->initComponentsByString( substr( $method, 6 ), $arguments )
            );

        // find one by
        } elseif( substr( $method, 0, 9 ) === 'findOneBy' ) {

            $filterFactory = new FindFilterComponentFactory( $this->propertyMapper );

            return $this->findByFilterComponents(
                $filterFactory::FIND_ONE,
                $filterFactory->initComponentsByString( substr( $method, 9 ), $arguments )
            );

        // remove by
        } elseif( substr( $method, 0, 8 ) === 'removeBy' ) {

            $filterFactory = new FindFilterComponentFactory( $this->propertyMapper );

            $found = $this->findByFilterComponents(
                $filterFactory::FIND_ALL,
                $filterFactory->initComponentsByString( substr( $method, 8 ), $arguments )
            );

            $this->remove( $found );
            return $this;

        } elseif( substr( $method, 0, 6 ) === 'sortBy' ) {

           $property = lcfirst( substr( $method, 6 ) );
           $direction = count( $arguments) > 0 ? $arguments[0] : SORT_ASC;
           $this->sort( $this->generateSortByPropertyClosure( $property, $direction ) );
           return $this;

       }

        throw new \BadMethodCallException("unknown method {$method}() on " . __CLASS__);

    }

    /**
     * Filter a container
     *
     * @param FindFilterComponentFactory::FIND_ALL | FindFilterComponentFactory::FIND_ONE $qty
     * @param Bond\Container\FindFilterComponent[]
     * @return object|Container
     */

    public function findByFilterComponents( $qty, array $filterComponents )
    {

        // don't use $this copy because we might not be returning a object of exactly the same type
        $output = $this->copy();

        // nothing to filter - return what we've got
        if( !$filterComponents ) {
            return $output;
        }

        // first attempt - this definately works
        $filterClosure = function( $obj ) use ( $filterComponents ) {

            $pass = true;
            foreach( $filterComponents as $filter ) {

//                try {
                    $filterResult = $filter->check( $obj );
//                } catch ( BadPropertyException $e ) {
//                    print_r( $obj );
//                    die();
//                }

                if( in_array( $filter->operation, array( 'AND', '', null ) ) ) {
                    $pass = ( $pass and $filterResult );
                } elseif( $filter->operation === 'OR' ) {
                    $pass = ( $pass or $filterResult );
                } else {
                    throw new \UnknownFilterOperatorException( "I don't know how to handle this operation yet. Sorry." );
                }

            }

            return $pass;

        };

        // go filter
        $output->filter( $filterClosure );

        // qty outputs
        if( $qty === FindFilterComponentFactory::FIND_ALL ) {
            return $output;
        }

        switch( $count = count( $output ) ) {
            case 0:
                return null;
            case 1:
                return $output->pop();
        }

        throw new IncompatibleQtyException( "{$count} entities found - can't return 'one'" );

    }

    /**
     * Set a containers class
     * @param string|Object|Repository|Container class
     * @return bool
     */
    public function classSet( $input )
    {

        if( is_object( $input ) ) {
            if( $input instanceof self ) {
                $class = $input->classGet();
            } else {
                $class = get_class( $input );
            }
        } else {
            $class = $input;
        }

        $currentClass = $this->classGet();
        if( $currentClass ) {
            if( $class !== $currentClass ) {
                throw new \LogicException( "Container has a class already ({$currentClass}) and it differs from your new one ({$class})" );
            }
            return false;
        }
        $this->class = $class;
        return true;
    }

    /**
     * Get the class of this container.
     * @return string Class | null
     */
    private function classGet()
    {
        return isset( $this->class )
            ? $this->class
            : (
                  ( $firstElement = $this->peak() )
                ? ( $this->class = get_class( $firstElement ) )
                : null
              )
            ;
    }

    /**
     * Split by class
     * @return array of containers
     */
    public function splitByClass()
    {

        $classes = array();
        foreach( $this->collection as $key => $entity ) {
            $classes[get_class($entity)][] = $key;
        }
        ksort( $classes );

        $output = array();
        foreach( $classes as $class => $splHashes ) {
            $container = $this->newEmptyContainer();
            $container->class = $class;
            $container->collection = array_intersect_key(
                $this->collection,
                array_flip( $splHashes )
            );
            $output[$class] = $container;
        }
        return $output;

    }

    /**
     * Pop element off collection
     * @return ContainerableInterface|null
     */
    public function pop()
    {
        return array_pop( $this->collection );
    }

    /**
     * Shift a element off collection
     * @return ContainerableInterface|null
     */
    public function shift()
    {
        return array_shift( $this->collection );
    }

    /**
     * Seems like I'm more and more imploding groups of entities on something
     * @param string $glue
     * @param mixed $propertyOrClosure
     */
    public function implode( $glue, $propertyOrClosure = null )
    {
        if( null === $propertyOrClosure ) {
            $pieces = $this->map('strval');
        } elseif ( $propertyOrClosure instanceof \Closure ) {
            $pieces = $this->map( $propertyOrClosure );
        } else {
            $pieces = $this->map( $this->generateGetPropertyClosure( $propertyOrClosure ) );
        }
        return implode( $glue, $pieces );
    }

    /**
     * Generate a new empty container of the same type as what is currently instantiated
     * @param string
     */
    public function newEmptyContainer()
    {
        $refl = new ReflectionClass($this);
        $container = $refl->newInstanceArgs(func_get_args());
        $container->classSet($this->class);
        $container->propertyMapper = $this->propertyMapper;
        return $container;
    }

    /**
     * Rewind the Iterator to the first element
     */
    public function rewind()
    {
        reset( $this->collection );
        return $this;
    }

    /**
    * Return the current element
    */
    public function current()
    {
        return current( $this->collection );
    }

    /**
    * Return the key of the current element
    */
    public function key()
    {
        return key( $this->collection );
    }

    /**
    * Move forward to next element
    */
    public function next()
    {
        next( $this->collection );
    }

    /**
    * Checks if current position is valid
    */
    public function valid()
    {
        $key = key($this->collection);
        return ( !is_null( $key ) && $key !== false );
    }

    /**
     * Count elements in the collection
     */
    public function count()
    {
        return count( $this->collection );
    }

    /**
     * Set the value at specified index to entity
     *
     * @param mixed $index
     * @param ContainerableInterface $entity
     */
    public function offsetSet( $index, $entity )
    {
        if( is_null( $entity ) ){
            return;
        }
        $this->add( $entity );
    }

    /**
    * Return whether the requested index exists.
    * @param mixed $offset
    */
    public function offsetExists( $index )
    {
        return isset( $this->collection[$index] );
    }

    /**
    * Unsets the value at the specified index.
    * @param mixed $offset
    */
    public function offsetUnset( $index )
    {
        unset( $this->collection[$index] );
    }

    /**
    * Returns the value at the specified index.
    * @param mixed $offset
    * @return mixed
    */
    public function offsetGet( $index )
    {
        if( !isset( $this->collection[$index] ) ){
            return null;
        }
        return $this->collection[$index];
    }

}