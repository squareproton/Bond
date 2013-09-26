<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Container;
use Bond\Container\Exception\IncompatibleContainerException;
use Bond\Exception\UnknownPropertyForMagicGetterException;
use Bond\Entity\Exception\EntityNotRegisteredException;
use Bond\Pg;
use Bond\MagicGetter;

use Bond\Repository;
use Bond\RecordManager;

class EntityManager implements \ArrayAccess, \Countable, \Iterator
{

    use MagicGetter;

    private $db;
    private $recordManager;
    private $options;
    private $eventEmitter;

    private $repos = [];
    private $registrations = [];
    private $names = [];

    public function __construct( Pg $db, $options = [], $eventEmitter )
    {
        $this->db = $db;
        $this->options = $options;
        $this->eventEmitter = $eventEmitter;
        $this->recordManager = new RecordManager($this);
    }

    public function find( $entity, $id )
    {
        return $this->getRepository($entity)->find($id);
    }

    public function make( $entity )
    {
        return $this->getRepository($entity)->make();
    }

    public function getRepository( $something )
    {

        // if we're passed a container
        if( is_object($something) and $something instanceof Container ) {
            if( !$something = $something->class ) {
                throw new IncompatibleContainerException(
                    "You've passed a vanilla container to links get. Can't determine a foreign repo from this"
                );
            }
        }
        $entityName = \Bond\get_unqualified_class( $something );

        if( !isset( $this->names[$entityName] ) ) {
            throw new EntityNotRegisteredException($entityName, $this);
        }

        $entityClass = $this->names[$entityName];

        // instantiate new repo
        if( !isset( $this->repos[$entityClass] ) ) {

            $reflRepo = new \ReflectionClass($this->registrations[$entityClass]);
            $repo = $reflRepo->newInstance( $entityClass, $this );
            $this->repos[$entityClass] = $repo;

        }

        return $this->repos[$entityClass];
    }

    /**
     * Register a entity.
     * @param String Fully qualified class name of Entity
     * @param String Fully qualified class name of Repository
     */
    public function register( $entityClass, $repositoryClass )
    {

        $entityName = \Bond\get_unqualified_class($entityClass);

        if( isset($this->names[$entityName]) ) {
            throw new EntityAlreadyRegisteredException( $entityClass, $repositoryClass, $this );
        }

        $this->names[$entityName] = $entityClass;
        $this->registrations[$entityClass] = $repositoryClass;
        reset( $this->registrations );

    }

    /**
     * Is registered?
     * @param Entity|String $key
     */
    public function isRegistered( $entity )
    {
        if( is_object($entity) ) {
            return isset( $this->registrations[get_class($entity)]);
        }

        return isset( $this->registrations[$entity] ) || isset( $this->names[\Bond\get_unqualified_class($entity)] );
    }

    /**
     * Standard magic getter
     */
    public function __get( $key )
    {
        if( property_exists( $this, $key ) ) {
            return $this->$key;
        }
        throw new UnknownPropertyForMagicGetterException( $this, $key );
    }

    /**
     * \Countable interface
     * @return int
     */
    public function count()
    {
        return count($this->registrations);
    }

    /**
     * \ArrayAccess interface
     */
    public function offsetSet($offset, $value) {
        throw new \LogicException("You can't set a repository using array access. This doesn't make any sense.");
    }
    public function offsetUnset($offset) {
        throw new \LogicException("You can't remove a repository from the record manager.");
    }
    public function offsetExists($offset) {
        return $this->isRegistered($offset);
    }
    public function offsetGet($offset) {
        return $this->getRepository($offset);
    }

    /**
     * \Iterator interface
     */
    function rewind() {
        return reset( $this->registrations );
    }
    function current() {
        return $this->getRepository( key($this->registrations) );
    }
    function key() {
        return key( $this->registrations );
    }
    function next() {
        return next( $this->registrations );
    }
    function valid() {
        return key($this->registrations) !== null;
    }

}