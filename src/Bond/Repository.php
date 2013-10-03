<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Container;
use Bond\Container\Exception\IncompatibleQtyException;
use Bond\Container\FindFilterComponentFactory;

use Bond\Entity\Base;
use Bond\Entity\DataType;
use Bond\Entity\Exception\BadKeyException;
use Bond\Entity\Exception\EntityHasNoPrimaryKeyException;
use Bond\Entity\Exception\EntityLinkException;
use Bond\Entity\Exception\EntityReferenceException;
use Bond\Entity\PropertyMapperEntityData;
use Bond\Entity\PropertyMapperEntityInitial;
use Bond\Entity\Types\DateTime;

use Bond\EntityManager;

use Bond\Exception\DepreciatedException;
use Bond\Exception\UnknownPropertyForMagicGetterException;

use Bond\RecordManager\Task\Normality as TaskNormality;

use Bond\Repository\BaseInterface;
use Bond\Repository\Exception\EntityNotMakeableException;
use Bond\Repository\Multiton;

use Bond\Pg;
use Bond\Pg\Result;
use Bond\Pg\Catalog\Link;

use Bond\Sql\Query;
use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlCollectionInterface;

/**
 * Description of Repository
 * @author pete
 */
abstract class Repository implements BaseInterface
{

    # class constants
    const PERSISTED = 1;
    const UNPERSISTED = 2;
    const ALL = 3;

    const CHANGED = 4;
    const UNCHANGED = 8;

    const WITH_CHILDREN = 16;
    const NO_CHILDREN = 32;

    # object generation
    const MAKEABLE = 0; // Repository::make() as expected
    const MAKEABLE_DISABLE = 1; // Repository::make() returns null
    const MAKEABLE_EXCEPTION = 2; // Repository::make() throws exception

    /**
     * EntityManager
     */
    protected $entityManager;

    /**
     * Bond\Pg
     */
    private $db;

    /**
     * Entity name
     * @var string
     */
    protected $entity;

    /**
     * Entity namespace
     * @var string
     */
    protected $entityNamespace;

    /**
     * Entity class
     * @var string
     */
    protected $entityClass;

    /**
     * reflEntityClass - Used to instantiate entities
     * @var \ReflectionClass
     */
    private $reflEntityClass;

    /**
     * reflEntityClass
     * @var \ReflectionProperty reflEntityManagerPropertyOfEntity
     */
    private $reflEntityManagerPropertyOfEntity;

    /**
     * Children
     */
    protected $children = array();

    /**
     * Optional array of type information. See below for more info
     * @var array
     */
    protected $dataTypes = array();

    /**
     * Repo's makeable state
     */
    protected $makeable = 0;

    /**
     * Standard constructor
     * @param $entityName
     */
    public function __construct( $entityClass, EntityManager $em )
    {

        $this->entity = get_unqualified_class( $entityClass );
        $this->entityClass = $entityClass;
        $this->entityNamespace = get_namespace( $entityClass );
        $this->entityManager = $em;
        $this->db = $this->entityManager->db;

        $this->reflEntityClass = new \ReflectionClass( $this->entityClass );

        // entities might not have the property entityManager accessible
        try {
            $this->reflEntityManagerPropertyOfEntity = $this->reflEntityClass->getProperty('entityManagerHash');
            $this->reflEntityManagerPropertyOfEntity->setAccessible(true);
        } catch ( \ReflectionException $e ) {
            $this->reflEntityManagerPropertyOfEntity = null;
        }

        foreach( $this->dataTypes as &$dataType ) {
            $dataType = DataType::unserialize($dataType);
        }

    }

    /**
     * Invalidate the repository cache
     */
    public function cacheInvalidate()
    {
        return 0;
    }

    /**
     * Lookup a entity based on it's 'primary key' or key column
     * This will either return null or a Entity
     * @param mixed $key
     * @param bool $disableLateLoading
     */
    public function find( $key, $disableLateLoading = null )
    {

        // $disableLateLoading default value management
        if( !is_bool( $disableLateLoading ) ) {
            $disableLateLoading = false;
        }

        // empty key
        if( is_null( $key ) or !is_scalar( $key ) or strlen( trim( $key ) ) === 0 ) {
            return null;
        }

        $class = $this->entityClass;

        try {
            return $this->entityFactory( $key, $disableLateLoading );
        } catch( \Exception $e ) {
            return null;
        }

    }

    /**
     * Lookup a entity based on it's 'primary key' or key column when passed a set.
     * This will always return a Container
     * @return Container
     */
    public function findBySet( Set $keySet )
    {

        $dataTypes = $this->dataTypesGet( DataType::PRIMARY_KEYS );

        if( count( $dataTypes ) == 0 ) {
            throw new \EntityPrimar("No primary keys for this entity. Don't know what key(s) to lookup on. Sorry.");
        }

        $identifiers = array();
        foreach( $dataTypes as $dataType ) {
            $identifiers[] = $this->db->quoteIdent( $dataType->name );
        }

        $keySet->sqlIdentifierSet(
            implode(
                "||'" . Base::KEY_SEPARATOR . "'||",
                $identifiers
            )
        );

        // lookup data
        $query = new Query(
            sprintf(
                "SELECT * FROM ONLY %s WHERE %s",
                 $this->db->quoteIdent( $this->__get('table') ),
                 '%keySet:%'
            ),
            array(
                'keySet' => $keySet
            )
        );

        $result = $this->db->query( $query );
        $datas = $result->fetch( Result::FLATTEN_PREVENT );

        return $this->initByDatas( $datas );

    }

    /**
     * Temp helper method which actually does the instantiation work and ensure our Entity has a link to the EntityManager
     * @return Bond\Base
     */
    private function entityFactory()
    {
        // this is a little tortuous but some constructor flows require the EntityManager
        // as this point in the refactor I'm unwilling to modify the constructor of the Entity
        // TODO. Fix this. Constructor injection
        $entity = $this->reflEntityClass->newInstanceWithoutConstructor();
        // add the entityManager
        if( $this->reflEntityManagerPropertyOfEntity ) {
            $this->reflEntityManagerPropertyOfEntity->setValue($entity, spl_object_hash($this->entityManager));
        }
        // call the original constructor
        call_user_func_array( [$entity, '__construct'], func_get_args() );
        return $entity;
    }

    /**
     * Returns a new entity of the correct type.
     * @return Base
     */
    public function make( array $data = null )
    {

        if( !$this->makeableSafetyCheck() ) {
            return null;
        }

        $entity = $this->entityFactory();
        if( $data !== null ) {
            $entity->set(
                $data,
                // as data is a array the second argument doesn't make much sense here
                null,
                // set the inital values and see that the correct input validation happens
                $entity->inputValidate
             );
        }
        return $entity;

    }

    /**
     * Check to see if a repository is makeable
     * @return bool;
     */
    protected function makeableSafetyCheck()
    {
        // makeable check
        switch( $this->makeable ) {
            case self::MAKEABLE_DISABLE:
                return false;
            case self::MAKEABLE_EXCEPTION:
                throw new EntityNotMakeableException("Making objects of this type is restricted. Are you using a view or some other derived data.");
        }
        return true;
    }

    /**
     * See \Bond\Repository\Multiton
     */
    public function rekey( Base $entity )
    {
        return false;
    }

    /**
     * See \Bond\Repository\Multiton
     */
    public function isNew( Base $entity, $state = null )
    {
        return null;
    }

    /**
     * See \Bond\Repository\Multiton
     */
    public function isAttached( Base $entity )
    {
        return null;
    }

    /**
     * See \Bond\Repository\Multiton
     */
    public function attach( Base $entity, &$restingPlace = null )
    {
        return null;
    }

    /**
     * See \Bond\Repository\Multiton
     */
    public function detach( Base $entity, &$detachedFrom = null )
    {
        return null;
    }

    /**
     * These methods are required for internal use (overloading with the multiton).
     * If you're working with a vanilla object and it isn't a multiton. Just use new Entity()
     * @param array $data
     * @return Entity
     */
    public function initByData( array $data )
    {
        return $this->entityFactory($data);
    }

    /**
     * Init a set of objects via datas array.
     * @param array datas
     * @return Entity\Container
     */
    public function initByDatas( array $datas )
    {
        return $this->makeNewContainer()
            ->add(
                array_map(
                    array( $this, 'initByData' ),
                    $datas
                )
            );
    }

    /**
     *
     * @param mixed $entity
     * @return null|array
     */
    public function propertiesOfTypeGet( $entity )
    {

        $entityClass = \Bond\get_unqualified_class( $entity );

        $properties = array();
        foreach( $this->dataTypesGet() as $_property => $dataType ) {
            if( $dataType->isNormalityEntity( $_entity ) and $_entity === $entityClass ) {
                $properties[] = $_property;
            }
        }

        return $properties;

    }

    /**
     * Return array of entities which are referenced by $entity
     * @param Base|Container $entity
     * @return array
     */
    public function referencedBy( $entity )
    {

        if( !( $entity instanceof Base || $entity instanceof Container ) ) {
            throw new \InvalidArgumentException("You can only lookup references by Entity or Container");
        }

        $class = $entity instanceof Container
            ? $entity->class
            : $entity;

        $properties = $this->propertiesOfTypeGet( $class );

        // found a entity?
        if( !$properties ) {
            throw new \LogicException(
                sprintf(
                    "Can't find which entity property for repository `%s` references entity of type `%s`",
                    $this->entity,
                    $entityClass
                )
            );
        } elseif( count( $properties ) > 1 ) {
            throw new \LogicException(
                sprintf(
                    "Multiple columns [%s] reference a entity of this type [%s]. This is ambiguous. Can't help you here.",
                    implode( ",", $properties ),
                    \Bond\get_unqualified_class( $entity )
                )
            );
        }

        $property = array_pop( $properties );

        // get references to this entity
        $filterComponent = $this->getFindFilterFactory()
            ->get( $property, null, $entity );

        return $this->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            array( $property => $filterComponent )
        );

    }

    /**
     * Check if Entity has link
     *
     * @param mixed $reference
     * @param mixed $referenceDetail
     *
     * @return boolean
     */
    public function hasReference( $reference, &$referenceDetail = null )
    {

        $referenceDetail = null;

        foreach( $this->__get('references') as $name => $detail ) {

            if( $name == $reference or $detail[0] == $reference ) {
                $referenceDetail = $detail;
                return $name;
            }

        }

        return null;

    }

    /**
     * Get all references
     *
     * @param Base|Container $entityOrContainer - Starting Entity.
     * @param mixed $foreignEntity - The type of target Entity we want to retrieve.
     * @param int $source - The source of the links, either PERSISTED, UNPERSISTED, CHANGED or ALL.
     *
     * @return Entity|Container|null - whatever is apropriate for the link type.
     */
    public function referencesGet( $entityOrContainer, $reference, $source = null )
    {

        if( !( $entityOrContainer instanceof Base || $entityOrContainer instanceof Container ) ) {
            throw new \InvalidArgumentException("You can only lookup references by Entity or Container");
        }

        if( !$this->hasReference( $reference, $detail ) ) {
            throw new EntityReferenceException( $this, $reference );;
        }

        $foreignRepo = $this->entityManager->getRepository( $detail[0] );

        $filterComponent = $this->getFindFilterFactory($source)
            ->get( $detail[1], null, $entityOrContainer );

        // find one / find all
        if( $entityOrContainer instanceof Container ) {
            $findQty = FindFilterComponentFactory::FIND_ALL;
        } else {
            $findQty = $detail[2] ? FindFilterComponentFactory::FIND_ALL : FindFilterComponentFactory::FIND_ONE;
        }

        return $foreignRepo->findByFilterComponents(
            $findQty,
            array( $detail[1] => $filterComponent ),
            $source
        );

    }

    /**
     * Check if Entity has a named link
     *
     * @param mixed $linkName or $foreignEntity
     * @param mixed $linkDetail - Link information
     * @return mixed
     */
    public function hasLink( $link, &$linkDetail = null )
    {

        $links = $this->__get('links');

        if( isset($links[$link])) {
            $linkDetail = $links[$link];
            return $link;
        }

        foreach( $links as $via => $linkDetail ){
            if( in_array( $link, $linkDetail->foreignEntities ) ) {
                return $via;
            }
        }

        return false;

    }

    /**
     * Get links
     *
     * @param Bond\Entity\Base|Bond\Container $entity - Entity||Container which is matches 'this' side of a link
     * @param string $link - The entity name of the link table we're going via
     * @param string $foreignEntity - Disambiguate which type of entity we want returned in the event of table inheritance. Default to parent|root entity
     * @param int $source - The source of the links, either PERSISTED, UNPERSISTED, CHANGED or ALL.
     * @param $accessViaForeignProperty - Set the 'property' on returned container
     *
     * @return Bond\Container
     */
    public function linksGet( $entity, $link, $foreignEntity = null, $source = null, $accessViaForeignProperty = true )
    {

        if( $entity instanceof Base || $entity instanceof Container ) {
            $entityRepo = $this->entityManager->getRepository($entity);
        } else {
            throw new \LogicException("You can only lookup links by Entity or Container");
        }

        if( !( $via = $entityRepo->hasLink( $link, $linkDetail ) ) ){
            throw new EntityLinkException(
                sprintf(
                    "Don't know about any link from `%s` to `%s`",
                    get_class($entity),
                    $link
                )
            );
        }

        $linkRepo = $this->entityManager->getRepository($via);

        $filterComponent = $this->getFindFilterFactory($source)
            ->get( $linkDetail->refSource[1], null, $entity );

        $linksContainer = $linkRepo->findByFilterComponents(
            FindFilterComponentFactory::FIND_ALL,
            [ $linkDetail->refSource[1] => $filterComponent ] ,
            $source
        );

        // have ranking column
        if( isset( $linkDetail->sortColumn ) ) {
            $linksContainer->sortByProperty( $linkDetail->sortColumn );
        }

        // return a container which, when iterated, returns the foreign entity and not the link
        if( $accessViaForeignProperty ) {

            // ahh, fuck it. This could be made faster but probably not simpler
            $output = $linksContainer->pluck( $linkDetail->refForeign[0], true );
            return $output->setPropertyMapper( PropertyMapperEntityData::class );

        }

        return $linksContainer;

    }

    /**
     * Look up a entity based on it's api key
     * @param mixed $value
     * @return entity |
     */
    public function findByApiKey( $value )
    {

        $apiOptions = $this->__get('apiOptions');

        if( !isset( $apiOptions['findByKey'] ) ) {
            return $this->find( $value, true );
        }

        $property = $apiOptions['findByKey'];

        $filter = $this->getFindFilterFactory()
            ->get( $property, null, $value );

        return $this->findByFilterComponents(
            FindFilterComponentFactory::FIND_ONE,
            array( $property => $filter ),
            self::ALL
        );

    }

    /**
     * Warning! Here be the magic....
     *
     * ->findAll()
     * ->findAllById( $id )
     * ->findOneById( $id )
     *
     * @return mixed
     */
    public function __call( $method, $arguments )
    {

        $prefixs = array(
            'findChangedBy',
            'findChanged',
            'findPersistedBy',
            'findPersisted',
            'findUnpersistedBy',
            'findUnpersisted',
            'findInitialBy',
            'findInitial',
            'findAllBy',
            'findAll',
            'findOneBy',
        );

        // iterate over prefixes' and build any filter components
        foreach( $prefixs as $callback ) {

            $requiresFilter = 'By' === substr( $callback, -2 );
            $_method = substr( $method, 0, strlen($callback) );
            $_by = substr( $method, strlen($callback) );

            // Got milk?
            if( $_method === $callback and ( $requiresFilter xor empty($_by) ) ) {
                break;
            }

            $callback = null;

        }

        if( !isset( $callback ) ) {
            throw new \InvalidArgumentException( "Don't know how to handle \$repository->{$method}(). Sorry." );
        }

        // build source
        if( strpos( $callback, 'Unpersisted' ) == 4 ) {
            $source = self::UNPERSISTED;
        } elseif( strpos( $callback, 'Persisted' ) == 4 ) {
            $source = self::PERSISTED;
        } elseif( strpos( $callback, 'Changed' ) == 4 ) {
            $source = self::CHANGED;
        } elseif( strpos( $callback, 'Initial' ) == 4 ) {
            $source = Base::INITIAL;
        } else {
            $source = null;
        }

        if( $requiresFilter ) {
            $filterComponents = $this->getFindFilterFactory($source)->initComponentsByString( $_by, $arguments );
        } else {
            $filterComponents = [];
        }

        $qty = stristr( $callback, 'one' ) ? FindFilterComponentFactory::FIND_ONE : FindFilterComponentFactory::FIND_ALL;

        return $this->findByFilterComponents( $qty, $filterComponents, $source );

    }

    /**
     * Filter components
     * @param string $qty
     * @param array $filterComponents
     * @param int Bitmask fo class constants
     * @return EntityContainer
     */
    public function findByFilterComponents( $qty, array $filterComponents, $source = null )
    {

        $source = $this->findByFilterComponentsSourceSetup($source);

        $output = $this->makeNewContainer();

        $profiler = new Profiler( __FUNCTION__ );
        $profiler->log("setup");

        if( $source & self::PERSISTED ) {

            throw new \Exception("Talk to Pete.");

            $cannotMatch = function (Base $entity) {
                return $entity->isNew();
            };

            // If all of the filter components can't match - that is return true - there isn't any point in going to the db
            $checkDatabase = !\Bond\array_check(
                function( $filterComponent ) use ( $cannotMatch ) {
                    var_dump( $filterComponent->getCannotMatch( $cannotMatch ) );
                    return $filterComponent->getCannotMatch( $cannotMatch );
                },
                $filterComponents,
                true
            );

            if( $checkDatabase ) {
                $output->add( $this->findByFilterComponentsDatabase( $filterComponents, $source ) );
            }

        }
        $profiler->log("database");

        return $this->findByFilterComponentsFormatReturnValue( $qty, $output );

    }

    /**
     * Instantiate a appropriate FindFilterFactory
     * @param int Bitfield Bond\Entity\Base::INITIAL and possibly others
     * @return Bond\Container\FindFilterFactory
     */
    private function getFindFilterFactory( $source = 0 )
    {
        $propertyMappers = [ PropertyMapperEntityData::class ];
        if( $source & Base::INITIAL ) {
            $propertyMappers[] = [ PropertyMapperEntityInitial::class ];
        }
        return new FindFilterComponentFactory( $propertyMappers );
    }

    /**
     * Set source defaults.
     * Helper method of findByFilterComponents
     * @param mixed $source
     */
    protected function findByFilterComponentsSourceSetup( $source )
    {

        // Set source defaults
        if( !( $source & ( self::PERSISTED | self::UNPERSISTED ) ) ) {
            $source += self::PERSISTED + self::UNPERSISTED;
        }

        if( !( $source & ( self::CHANGED | self::UNCHANGED ) ) ) {
            $source += self::CHANGED + self::UNCHANGED;
        }

        if( !( $source & ( self::WITH_CHILDREN | self::NO_CHILDREN ) ) ) {
            $source += self::WITH_CHILDREN;
        }

        if( !( $source & ( Base::DATA | Base::INITIAL ) ) ) {
            $source += Base::DATA;
        }

        return $source;

    }

    /**
     * Format the return value of a findByFilterComponents query
     * @param self::FIND_ALL|FIND_ONE $qty
     * @param Container $output
     * @return Container
     */
    public function findByFilterComponentsFormatReturnValue( $qty, Container $output )
    {

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
     * Find a entity having been passed the filters.
     *
     * @param array $filterComponents
     * @return Base|Container
     */
    protected function findByFilterComponentsDatabase( array $filterComponents, $source )
    {

        // hit the database and find something
        $query = $this->buildQuery( $this->db, $filterComponents );

        // get records from database
        $result = $this->db->query( $query )->fetch( Result::FLATTEN_PREVENT );

        $isChangedFilterCallback = $this->getFilterByIsChangedCallback($source);

        $output = $this->initByDatas( $result )
            ->filter($isChangedFilterCallback)
            ->each(function($entity){
                $entity->startDataInitialStore();
            });

        // add records from child tables if they exist
        if( $source & self::WITH_CHILDREN ) {
            foreach ( $this->children as $child ) {
                $childRepo = $this->entityManager->getRepository($child);
                $output->add(
                    $childRepo->findByFilterComponentsDatabase(
                        $filterComponents,
                        $source
                    )
                );
            }
        }

        return $output;

    }

    /**
     * Return a array of data containing information about a record
     * Used by Entity\Base->load()
     * @param string|array $key
     * @return array
     */
    public function data( $key )
    {

        // string, key clause
        if( is_scalar( $key ) ) {

            $dataTypes = $this->dataTypesGet( DataType::PRIMARY_KEYS );
            try {
                $key = array_combine(
                    array_keys( $dataTypes ),
                    explode( Base::KEY_SEPARATOR, $key )
                );
            } catch ( \Exception $e ) {
                throw new BadKeyException(
                    sprintf(
                        "Passed key `%s` needs %d fragments; it has %d",
                        $key,
                        count( $dataTypes ),
                        count( explode( Base::KEY_SEPARATOR, $key ) )
                    )
                );
            }

        // is array
        } elseif( is_array( $key ) and count( $key ) > 0 ) {

            $dataTypes = $this->dataTypesGet( array_keys( $key ) );

        // bad argument
        } else {
            throw new \InvalidArgumentException("Can't lookup data for entity with this argument -> " . print_r( $key, true ) );
        }

        // we got the right amount of keyFragments?
        if( count($key) !== count($dataTypes) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    "The passed key (%s) can't be married to the repository keys (%s). Epic fail.",
                    print_r($key, true),
                    implode(',', array_keys($dataTypes) )
                )
            );
        }

        // build where clause
        $where = array();
        foreach( $dataTypes as $name => $dataType ) {

            $where[] = sprintf(
                "%s = %s",
                $this->db->quoteIdent( $name ),
                $dataType->toQueryTag()
            );
            $data[$name] = $key[$name];

        }

        // build query
        $query = new Query(
            sprintf(
                "SELECT * FROM ONLY %s WHERE %s",
                $this->db->quoteIdent( $this->__get('table') ),
                implode( ' AND ', $where )
            ),
            $data
        );

        $result = $this->db->query( $query );
        $data = $result->fetch( Result::FETCH_SINGLE | Result::FLATTEN_PREVENT );

        return $data;

    }

    /**
     * Build a Query object
     * @param array $filterComponents
     * @return Bond\Pg\Query
     */
    protected function buildQuery( QuoteInterface $db, $filterComponents )
    {

        // primary keys
        $filterTags = array();
        $dataTypes = $this->dataTypesGet();

        // have we got components that we can't handle
        if( $dataTypesMissing = array_diff( array_keys( $filterComponents ), array_keys( $dataTypes ) ) ) {
            throw new \LogicException(
                sprintf(
                    "The following keys `%s` don't exist in %s. You can't find a entity with this.",
                    implode(',', $dataTypesMissing ),
                    $this->table
                )
            );
        }

        // add the datatype tag to the filter component
        foreach( $filterComponents as $name => $component ) {
            $component->tag = $dataTypes[$name]->toQueryTag();
        }

        $data = array();

        # system column tableoid can tell us which table a row comes from
        # http://www.postgresql.org/docs/9.1/static/ddl-inherit.html
        $sql = sprintf(
            "SELECT * FROM ONLY %s %s",
            $db->quoteIdent( $this->table ),
            $this->buildQueryWhereClause( $db, $filterComponents, $data )
        );

        $query = new Query( $sql, $data );

        return $query;

    }

    /**
     * Helper function for ->buildQuery(). Where clause.
     * @param array $filterComponents
     * @param array $data
     * @return string
     */
    protected function buildQueryWhereClause( QuoteInterface $db, $filterComponents, array &$data = array() )
    {

        // anything to do?
        if( !$filterComponents ) {
            return '';
        }

        $output = 'WHERE';

        // build up output
        foreach( $filterComponents as $name => $component ) {

            $fragment = '';

            $operation = $component->operation;
            $value = $component->value;

            // AND OR operator keywords
            if( $operation ) {
                $fragment .= " {$component->operation}";
            }

            // we going to use a `name` = methodology
            // something more specific?
            if( $value instanceof Set ) {

                $value->sqlIdentifierSet(
                    $db->quoteIdent( $name )
                );
                $comparitor = '';

            } else {

                $fragment .= sprintf(
                    " %s %s",
                    $db->quoteIdent( $name ),
                    ( $value instanceof SqlCollectionInterface ) ? 'IN' : '='
                );

            }

            // null safe comparison
            if( is_null( $component->value ) ) {
                $fragment = rtrim( $fragment, '=' );
                $fragment .= " IS NULL";
            } else {
                $fragment .= " {$component->tag}";
                $data[$name] = $component->value;
            }

            $output .= " {$fragment}";

        }

        return $output;

    }

    /**
     * Standard __get()
     * @param scalar $key
     * @return mixed
     */
    public function __get( $key )
    {
        switch( $key ) {
            case 'entity':
            case 'entityClass':
            case 'entityNamespace':
            case 'initialProperties':
            case 'db':
                return $this->$key;
            case 'table':
                return static::TABLE;
            case 'links':
                if( is_string( $this->links ) ) {
                    $links = json_decode( $this->links, true );
                    $this->links = array();
                    foreach( $links as $linkName => $link ) {
                        $this->links[$linkName] = Link::fromArray( $link );
                    }
                }
                return $this->links;
            case 'formOptions':
            case 'apiOptions':
            case 'normality':
            case 'references':
                return json_decode( $this->$key, true );
            case 'keys':
                return array_keys( $this->dataTypes );
        }
        throw new UnknownPropertyForMagicGetterException( $this, $key );
    }

    /**
     * Helper function. Get dataTypes for this Entity.
     * @return array
     */
    public function dataTypesGet( $filter = null )
    {

        // array of string
        if( func_num_args() > 1 and \Bond\array_check( 'is_string', func_get_args() ) ) {
            $filter = func_get_args();
        }

        // filter constants
        if( is_int( $filter ) ) {

            $dataTypes = array();

            if( $filter & DataType::PRIMARY_KEYS ) {
                $dataTypes += array_filter(
                    $this->dataTypes,
                    function( $dataType ) {
                        return $dataType->isPrimaryKey();
                    }
                );
            }

            if( $filter & DataType::FORM_CHOICE_TEXT ) {
                $dataTypes += array_filter(
                    $this->dataTypes,
                    function( $dataType ) {
                        return $dataType->isFormChoiceText();
                    }
                );
            }

            if( $filter & DataType::AUTOCOMPLETE ) {
                $dataTypes += array_filter(
                    $this->dataTypes,
                    function( $dataType ) {
                        return $dataType->isAutoComplete();
                    }
                );
            }

            // todo. build a datatype from the $this->links et al
            if( $filter & DataType::LINKS ) {
            }

            return $dataTypes;

        }

        if( is_string( $filter ) ) {
            $filter = array( $filter );
        }

        if( is_array($filter) ) {

            $result = array_intersect_key( $this->dataTypes, array_flip( $filter ) );

            if ( count($result) != count($filter) ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Unknown columns "%s" passed to %s->dataTypesGet()',
                        implode( '","', array_diff( $filter, array_keys( $result ) ) ),
                        $this->entity
                    )
                );
            }

            return $result;
        }

        if( $filter instanceof \Closure ) {
            return array_filter( $this->dataTypes, $filter );
        }

        return $this->dataTypes;
    }

    /**
     * Filter entities by their changed status.
     * @param bool $changed. Remove entities which are changed
     * @param bool $uchanged. Remove unchanged entities
     * @return $this
     */
    public function getFilterByIsChangedCallback( $source )
    {

        $changed = !( $source & self::CHANGED );
        $unchanged = !( $source & self::UNCHANGED );

        if( $changed and $unchanged ) {
            return function() {
                return false;
            };
        }

        if( !$changed and !$unchanged ) {
            return function() {
                return true;
            };
        }

        return function ($entity) use ( $changed ) {
            return $entity->isChanged() !== $changed;
        };

    }

    /**
     * Get a repository constant from its name
     * @param string Name of constant
     * @return scalar
     */
    public function constantGet( $name )
    {
        return constant( "static::{$name}" );
    }

    /**
     * Helper function. Entity static callback.
     * @param string $method
     * @param mixed argument
     */
    protected function makeStaticCallToEntity( $method )
    {
        return call_user_func_array(
            sprintf(
                "%s::%s",
                $this->entityClass,
                $method
            ),
            array_slice( func_get_args(), 1 )
        );
    }

    /**
     * Get a new Container
     * @param string Class
     * @return Bond\Container
     */
    protected function makeNewContainer()
    {
        $container = new Container();
        $container->classSet($this->entityClass);
        $container->setPropertyMapper( PropertyMapperEntityData::class );
        return $container;
    }

}