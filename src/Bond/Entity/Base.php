<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity;

use Bond\Entity\Exception\BadKeyException;
use Bond\Entity\Exception\BadPropertyException;
use Bond\Entity\Exception\EntityMissingEntityManagerException;
use Bond\Entity\Exception\LateLoadException;
use Bond\Entity\Exception\ReadonlyException;
use Bond\Entity\Exception\UnexpectedPropertyException;

use Bond\RecordManager\ChainSavingInterface;
use Bond\RecordManager\Task;

/**
 * Base class for managing, manipulating and saving objects
 */
abstract class Base implements ChainSavingInterface, \Iterator, \ArrayAccess, \Countable, \Serializable, \JsonSerializable
{

    /**
     *  Class constants
     */

    # validation
    const VALIDATE_EXCEPTION = 0;
    const VALIDATE_STRIP = 1;
    const VALIDATE_DISABLE = 2;

    # readonly
    const FULL_ACCESS = 0; // can create, update, delete anything at any time
    const READONLY_DISABLE = 1; // prevent updates from occouring silently
    const READONLY_EXCEPTION = 2; // prevent updates by throwing exception
    const READONLY_ON_PERSIST = 4; // prevent updates if object has been persisted. Think logs.

    # late loading of date
    const LOAD_DATA_LATE = 1;
    const LOAD_DATA_IMMEDIATELY = 0;

    # source of data
    const DATA = 16;
    const INITIAL = 32;

    const KEY_SEPARATOR = '|';

    /**
     * Contains all entity properties. Keys expected to be very similar, but not nessicarily identical,
     * to the keys returned in array returned from $this->data()
     * See above for list of reserved array keys.
     *
     * @var array
     */
    protected $data = array();

    /**
     * Copy of original data. This will only be stored after a call to $this->markAsInitialData()
     * @var array
     */
    private $dataInitial = null;

    /**
     * Unsetable properties
     * By default id is assumed to be the primary key. Now overloaded by normality as required.
     * @var array
     */
    protected static $unsetableProperties = ['id'];

    /**
     * Additional properties that are avaliable to this entity via ->get() and ->set()
     * @var array
     */
    protected static $additionalProperties = [];

    /**
     * Object properties which comprise this Entity's key.
     * @var array
     */
    protected static $keyProperties = ['id'];

    /**
     * Late loading configuration state
     * @var LOAD_DATA_LATE|LOAD_DATA_IMMEDIATELY
     */
    protected static $lateLoad = self::LOAD_DATA_LATE;

    /**
     * Late load property. If the late load property is accessed we don't need to
     * load() entity we can just return the lateLoadKey
     * Id is presumed to be the default primary key.
     * @var propertyName
     */
    protected static $lateLoadProperty = 'id';

    /**
     * Is loaded state. Because the constructor is public we want to prevent clobbering of data with multiple calls to __construct
     * @var bool
     */
    private $isLoaded = false;

    /**
     * Contains the md5 hash of $this->data (serialized) immediately after object instantiation.
     * Allows detection of a changed object.
     */
    private $checksum = null;

    /**
     * Late load key. If this is set the object hasn't been loaded yet.
     * @var scalar
     */
    private $lateLoadKey = null;

    /**
     * A uid helper instance if this is required. Ie, for new objects.
     * Not used at the moment. Planned usage in the future for Normality chain and reference saving.
     * @var Bond\Entity\Uid
     */
    private $uid = null;

    /**
     * Validate the input
     * @var $inputValidate
     */
    protected static $inputValidate = self::VALIDATE_EXCEPTION;

    /**
     * Is readonly
     */
    protected static $isReadOnly = self::FULL_ACCESS;

    // This is almost certainly going to be depreciated in favour of a more doctrine esque approach.
    // In this transitional period we're going to add this so that ::r() (and any equivalents) still works.
    // Exception thrown if this isn't set
    /**
     * EntityManager
     */
    protected $entityManager;

    /**
     * Instantiate a new Entity
     * Takes a single input (iterating over it setting properties as required), iterates over it setting properties
     *
     * @param array $data
     * @param bool $validateInput
     */
    public function __construct( $data = null, $disableLateLoading = false )
    {

        // new object
        if( is_null( $data ) ) {

            $this->uid = new Uid( $this );
            $this->checksum = null;
            $this->isLoaded = true;
            return;

        // late loading
        } elseif( is_scalar( $data ) ) {

            $this->lateLoadKey = $data;

            if( static::$lateLoad === static::LOAD_DATA_IMMEDIATELY or $disableLateLoading ) {
                $this->load();
            } else {
                $this->data[static::$lateLoadProperty] = $data;
            }
            $this->isLoaded = true;
            return;

        // properties array
        } elseif( is_array( $data ) ) {

            if( !$this->isLoaded or isset( $this->lateLoadKey ) ) {

                // we're not late loading
                $this->__initInputValidate( $data );

                foreach( $data as $key => $value ) {
                    $this->data[$key] = $value;
                }

                $this->lateLoadKey = null;
                $this->checksumReset();
                $this->isLoaded = true;

            }

            return;

        }

        throw new \InvalidArgumentException(
            sprintf(
                "Can't instantiate a Entity with argument `%s`.",
                print_r( $data, true )
            )
        );

    }

    /**
     * Constructor input validation.
     * External to the constructor so that the constructor may itself be overloaded more easily.
     *
     * Does base input validation for the constructor.
     * Checks, that $data is a array.
     * Performs input validation as per the entity constructs in static::$inputValidate
     *
     * @return bool Success
     */
    protected function __initInputValidate( array &$data )
    {

        // $data must be a array
        if( !is_array( $data ) ) {
            throw \Exception( "Entity\Base expects a array for it's first argument" );
        }

        // check to see if any keys exist in $this->data that don't in $data
        switch( static::$inputValidate ) {

            case self::VALIDATE_DISABLE:
                break;

            case self::VALIDATE_STRIP:

                $data = \array_intersect_key( $data, $this->data );
                break;

            case self::VALIDATE_EXCEPTION:

                if( $extraKeys = array_diff_key( $data, $this->data ) ) {
                    throw new UnexpectedPropertyException( sprintf(
                        "Entity\Base via %s is not happy with undeclared keys in \$data input array.\nExtras keys not present in \$this->data: %s.\nPassed: %s",
                        get_called_class(),
                        implode( ', ', array_keys( $extraKeys ) ),
                        print_r( $data, true )
                    ));
                }

        }

        return true;

    }

    /**
     * Make a shallow copy of a object clearing it's key fields
     * @param bool # TODO. Pete/ This should probably be Bond\Schema property
     * @return Base
     */
    public function copy( $deep = false )
    {
        $data = $this->data;
        foreach( static::$keyProperties as $key ) {
            $data[$key] = null;
        }

        foreach( $data as $key => $value ) {
            if( is_object( $value ) and !( $value instanceof self ) ) {
                $data[$key] = clone $value;
            }
        }

        // have repository?
        try {
            if( $repository = $this->r() ) {
                $entity = $repository->make( $data );
                return $entity;
            }
        } catch ( EntityMissingEntityManagerException $e ) {
        }

        $entity = clone $this;
        $entity->data = $data;

        return $entity;
    }

    /**
     * If object is late loaded.... load now....
     * @return this;
     */
    public function load( $data = null )
    {

        if( !$this->isLoaded() ) {

            // Get repository. Sadly lateloading introduces a dependancy on repository.
            // I can't see any way round this.
            $repository = $this->r();

            if( $data = $repository->data( $this->lateLoadKey ) ) {
                $this->__construct( $data );
                $this->lateLoadKey = null;
            } else {
                throw new LateLoadException("Object late loaded with a invalid key `{$this->lateLoadKey}`");
            }

        }
        return $this;

    }

    /**
     * Serialize a string representation of this object
     * @return string
     */
    public function serialize()
    {
        return serialize(
            array(
                $this->data,
                $this->lateLoadKey,
            )
        );
    }

    /**
     * Unserialize.
     * @param string $data
     */
    public function unserialize( $data )
    {
        $data = unserialize( $data );
        $this->data = $data[0];
        $this->lateLoadKey = $data[1];
    }

    /**
     * Implments \JsonSerialize
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Return a checksum of the object currently as is
     * @return string 32char long hexedecimal number
     */
    public function checksumGet()
    {
        return md5( serialize($this->data) );
    }

    /**
     * After a object has been persisted its checksum need updating to reflect this.
     * return $this;
     */
    public function checksumReset()
    {

        $this->checksum = $this->checksumGet();
        return $this;
    }

    /**
     * Determine if a object has changed
     * @return bool
     */
    public function isChanged()
    {
        return $this->isLoaded() and $this->checksum !== $this->checksumGet();
    }

    /**
     * Is this object new? This is essentially a helpful wrapper for Repository::init( $this )->isNew( $this ); Reads better doesn't it.
     * @return booly
     */
    public function isNew()
    {
        try {
            if( $repository = $this->r() ) {
                return $repository->isNew( $this );
            }
        } catch ( EntityMissingEntityManagerException $e ) {
        }
        throw new \LogicException( "We've not got a repository configured for this entity. I can't answer this question." );
    }

    /**
     * Is this object a zombie?
     * A zombie is a object that is missing a property which makes is useless or meaningless.
     * Think a address without a name or contact or a phone number with no other context.
     * @return bool
     */
    public function isZombie()
    {
        return false;
    }

    /**
     * Is this object attached to a repository? This is essentially a helpful wrapper for Repository::init( $this )->isAttached( $this );
     * @return booly
     */
    public function isAttached()
    {
        try {
            if( $repository = $this->r() ) {
                return $repository->isAttached( $this );
            }
        } catch ( EntityMissingEntityManagerException $e ) {
        }

        throw new \LogicException( "We've not got a repository configured for this entity. I can't answer this question." );
    }

    /**
     * Mark a entity as deleted
     * @return void
     */
    public function markDeleted()
    {
        try {
            if( $repository = $this->r() ) {
                $repository->detach( $this );
            }
        } catch ( EntityMissingEntityManagerException $e ) {
        }
        $this->checksum = null;
        return $this;
    }

    /**
     * Mark a entity as persisted
     * @return void
     */
    public function markPersisted()
    {
        try {
            if( $repository = $this->r() ) {
                $repository->isNew( $this, false );
            }
        } catch ( EntityMissingEntityManagerException $e ) {
        }
        $this->checksumReset();
        $this->clearDataInitialStore();
        return $this;
    }

    /**
     * Mark object as required to store its initial data
     * @return this
     */
    public function startDataInitialStore()
    {
        if( is_null( $this->dataInitial ) ) {
            $this->dataInitial = array();
        }
        return $this;
    }

    /**
     * Clear a object's inital dataStore. Think, RecordManager::Persist
     * @return this
     */
    public function clearDataInitialStore()
    {
        $this->dataInitial = array();
        return $this;
    }

    /**
     * Has this entity be 'loaded' yet? See, LATE_LOAD
     * @return bool
     */
    public function isLoaded()
    {
        return !isset( $this->lateLoadKey );
    }

    /**
     * Is this object readonly?
     * @return int|booly
     */
    public static function isReadonly()
    {
        return static::$isReadOnly;
    }

    /**
     * Is this object a link object. With respect to db design this is a bit subjective.
     * At the moment this returns true if a Entity is of type Entity\Link
     * @return bool
     */
    public function isLink()
    {
        return false;
    }

    /**
     * Get a uid for a object. This uid has the following properties.
     *
     *   1. Two different objects won't share the same uid simultaneously
     *   2. In normal script usage a object's uid won't change in the script lifecycle.
     *      To fiddle this you'd need to diddling hard with setDirect and the only thing that should be doing that is the record manager.
     *   3. A objects uid is only good for the lifetime of a script.
     *   4. The uid is no good for looking up a object. This is a strictly one-way thing
     *   5. The numbers are not deep and meaningfull. You can't infer anything from them. They are just, well, numbers.
     *
     */
    public function uidGet()
    {

        // got uid
        if( isset( $this->uid ) ) {

            return $this->uid->key;

        // got key
        } elseif( $key = $this->keyGet( $this ) ) {

            return $key;

        }

        $this->uid = new Uid( $this );
        return $this->uid->key;

    }

    /**
     * Cast a entity to string
     * @return string
     */
    public function __toString()
    {
        return (string) $this->keyGet( $this );
    }

    /**
     * Get object properties that aren't in any way magically determined
     *
     * @param string $key
     */
    public function __get( $key )
    {

        switch( $key ) {

            case 'data':

                $this->load();

                // todo call get() on all the properties to instantiate any child objects
                return $this->data;

            case 'keys':

                return array_keys( $this->data );
                break;

            // static properties
            case 'lateLoadProperty':
            case 'lateLoad':
            case 'isReadOnly':
            case 'inputValidate':
            case 'unsetableProperties' :
            case 'additionalProperties' :

                return static::$$key;

            case 'namespace':

                return \Bond\get_namespace( get_called_class() );

        }

        throw new \InvalidArgumentException( "`{$key}` is not known to magic method __get()" );

    }

    /**
     * Get a entity property
     * Manages calbacks named "get_{$key}"
     *
     * @param mixed $key Key of $this->data
     * @param booly $inputValidate, See, self::VALIDATE_EXCEPTION?
     * @param mixed $source const DATA | INITIAL, defaults DATA
     * @param \Bond\RecordManager\Task $task
     *
     * @return mixed
     */
    public function get( $key, $inputValidate = null, $source = null, Task $task = null  )
    {

        // get a array
        if( is_array($key) ) {
            $output = array();
            foreach( $key as $_key ) {
                $output[$_key] = $this->get( $_key, $inputValidate, $source );
            }
            return $output;
        }

        // default input validate settings
        if( $inputValidate === null ) {
            $inputValidate = self::$inputValidate;
        }

        // default $source
        if( $source === null ) {
            $source = self::DATA;
        }

        // Don't bother loading object if you're only interested in it's lateload property
        if( $key !== static::$lateLoadProperty ) {
            $this->load();
        }

        if( !is_scalar( $key ) ) {
            throw new \Bond\Exception\BadType( $key, 'scalar' );
        }

        switch( true ) {

            case array_key_exists( $key, $this->data ):

                // we getting our data from dataInitial
                if( $source === self::INITIAL ) {

                    if( is_array( $this->dataInitial ) and array_key_exists( $key, $this->dataInitial ) ) {
                        $source = self::INITIAL;
                        return $this->dataInitial[$key];
                    }

                } elseif( $source !== self::DATA ) {
                    throw new \InvalidArgumentException("Bad source, must be either DATA || INITIAL - passed `{$source}`" );
                }

                $source = self::DATA;

                $callback = array( $this, "get_{$key}" );
                return is_callable( $callback )
                     ? call_user_func_array( $callback, array( &$this->data[$key], $task ) )
                     : $this->data[$key]
                     ;

        }

        if( $inputValidate === self::VALIDATE_EXCEPTION ) {
            throw new BadPropertyException( $key, $this, "Cannot get()." );
        }

        return null;

    }

    /**
     * Is this object readonly?
     * @return int|booly
     */
    protected function readOnlySafetyCheck()
    {

        switch( static::$isReadOnly ) {

            case self::FULL_ACCESS:
                return false;
            case self::READONLY_DISABLE:
                return true;
            case self::READONLY_EXCEPTION:
                throw new ReadonlyException( "Entity is readonly" );
            case self::READONLY_ON_PERSIST:
                if( $this->isNew() !== false ) {
                    return false;
                }
                throw new ReadonlyException( "Entity has been persisted and is now readonly" );

            default:
                throw new \InvalidArgumentException( "Entity self::\$isReadonly is has a invalid argument" );

        }

    }

    /**
     * Sets a entity property.
     * Manages calbacks named "set_{$key}"
     *
     * @param scalar $key Key of $this->data.
     * @param mixed $value $value to set
     *
     * @return bool|int Have we set a value?
     */
    public function set( $key, $value = null, $inputValidate = null )
    {

        // decide on the level of input validation
        if( $inputValidate === null ) {
            $inputValidate = static::$inputValidate;
        }

        // set a array / Symfony ParameterBag of properties
        if( is_array($key) || ( $key instanceof \Traversable ) ) {
            $numSet = 0;
            foreach( $key as $_key => $_value ) {
                $numSet += call_user_func( array($this,__FUNCTION__), $_key, $_value, $inputValidate ) ? 1 : 0;
            }
            return $numSet;
        }

        // readonly safety check
        if( $this->readOnlySafetyCheck() ) {
            return false;
        }

        // You can't set a objects id externally
        if( in_array( $key, static::$unsetableProperties ) ) {
            return false;
        }

        // late load. You can't set a property on a unloaded object
        $this->load();

        switch( true ) {

            // got key or we're configured to accept any inputs
            case $inputValidate === self::VALIDATE_DISABLE:
            case array_key_exists( $key, $this->data ):

                if( $value === '' ) {
                    $value = null;
                }

                // validate item ?
                // implementation undecided
                $callback = array( $this, "set_{$key}" );

                $value = is_callable( $callback )
                    ? call_user_func( $callback, $value, $inputValidate )
                    : $value
                    ;

                // anything changed?
                if( isset( $this->data[$key] ) and $this->data[$key] === $value ) {
                    return false;
                }

                $this->initalPropertySet( $key, $value );
                $this->data[$key] = $value;
                return true;

            // unknown property. Class configured to ignore.
            case $inputValidate === self::VALIDATE_STRIP:

                return false;

            // unknown property. Class configured for Exception.
            case $inputValidate === self::VALIDATE_EXCEPTION:

                throw new BadPropertyException( $key, $this, "set() VALIDATE_EXCEPTION." );

            // odd that we should have got this far.
            // debugging check - ensure $inputValidate is one of our allowed inputs
            case !in_array( $inputValidate, array( self::VALIDATE_DISABLE, self::VALIDATE_STRIP, self::VALIDATE_EXCEPTION ), true ):

                throw new \InvalidArgumentException("Bad argument `{$inputValidate}` argument passed to ->set(,,\$inputValidate);");
                return false;

        }

        return false;

    }

    /**
     * InitialPropertiesSet helper method
     */
    public function initalPropertySet( $key, $value)
    {

        // Is this one of our initialProperties
        // Is it different. Should we store it?
        if( is_array( $this->dataInitial ) and $repository = $this->r() and in_array( $key, $repository->initialProperties ) ) {

            $initialValue = $this->get( $key, self::READONLY_EXCEPTION, $source = self::DATA );

            // only bother setting if the new value differs from the old and we don't have a dataInitial yet
            if( $initialValue !== $value and !array_key_exists( $key, $this->dataInitial ) ) {
                $this->dataInitial[$key] = $initialValue;
                return true;
            }

        }

        return false;

    }

    /**
     * Sets a entities properties driectly to $this->data.
     * This allows RecordManager (which should really know what it is doing!) to set properties other code can't reach.
     * Don't abuse this!! This bypasses all validation!
     *
     * @param scalar $key
     * @param mixed $scalar
     *
     * @return bool Did this set go through?
     */
    public function setDirect( $key, $value = null )
    {

        // some functions (eg, the record manager under some circumstances)
        // might pass this a NULL. Handle this.
        if( $key === null ) {
            return 0;
        }

        // repository manage
        $keyBefore = $this->keyGet( $this );

        $numSet = 0;

        // set a array of properties
        if( is_scalar($key) ) {
            $key = array( $key => $value );
        }

        // is not scalar (I suppose this might also take a iterable. Not sure how to test against that).
        foreach( $key as $_key => $value ) {

            // lateload?
            if( isset( $this->lateLoadKey ) and $_key === static::$lateLoadProperty ) {
                $this->lateLoadKey = $value;
            } else {
                // $this->load();
            }

            if( array_key_exists( $_key, $this->data ) ) {
                $this->data[$_key] = $value;
                $numSet++;
            }

        }

        $keyAfter = $this->keyGet( $this );

        try {
            if( $keyBefore !== $keyAfter and $repository = $this->r() ) {
                $repository->rekey( $this );
            }
        } catch ( EntityMissingEntityManagerException $e ) {
        }

        return $numSet;

    }

    /**
     * Unset a class property from $this->data
     * @param mixed $key
     * @return bool
     */
    public function unsetProperty( $key )
    {

        // readonly safety check
        if( $this->readOnlySafetyCheck() ) {
            return 0;
        }

        if( is_scalar( $key ) ) {
            $keys = array( (string) $key );
        } elseif( is_array( $key ) ) {
            $keys = array_map( 'strval', $key );
        } else {
            throw new \InvalidArgumentException("Bad argument passed to " .__FUNCTION__ );
        }

        $numUnset = 0;

        // remove unsettable properties from this array
        $keys = array_diff( $keys, static::$unsetableProperties);

        // iterate over the keys
        foreach( $keys as $key ) {

            if( array_key_exists( $key, $this->data ) ) {

                if( $key === static::$lateLoadProperty and isset( $this->lateLoadKey ) ) {
                    unset( $this->lateLoadKey );
                    $numUnset++;
                } else {
                    $this->load();
                    if( isset( $this->data[$key] ) ) {
                        $this->data[$key] = null;
                        $numUnset++;
                    }
                }

            }

        }

        return $numUnset;

    }

    /**
     * Get the repository for this object
     * @return Bond\Repository
     */
    public function r()
    {
        if( !$this->entityManager ) {
            throw new EntityMissingEntityManagerException($this);
        }
        return $this->entityManager->getRepository($this);
    }

    /**
     * Isset for class properties from $this->data
     * @param mixed $key
     */
    public function issetProperty( $key )
    {
        if( $key === static::$lateLoadProperty and isset( $this->lateLoadKey ) ) {
            return true;
        }
        $this->load();
        return isset( $this->data[$key] );
    }

    /**
     * Key entity `key`
     * @param array|Entity\Base $data
     * @return array
     */
    public static function keyGet( $data )
    {

        // Is a Entity object ?
        $class = get_called_class();
        if( $data instanceof $class ) {

            $keys = array();
            foreach( static::$keyProperties as $property ) {

                $value = $data->get($property);

                // resolve entities back down to their key
                if( $value instanceof Base ) {
                    $value = call_user_func(
                        array( get_class($value), 'keyGet' ),
                        $value
                    );
                }

                $keys[$property] = $value;

            }

         // is a data array
        } elseif( is_array( $data ) ) {
            $keys = array_intersect_key(
                $data,
                array_flip( static::$keyProperties )
            );
        }

        // build and return the key
        if( isset( $keys ) ) {

            $key = implode( self::KEY_SEPARATOR, $keys );
            return strlen( trim( $key, self::KEY_SEPARATOR ) ) === 0
                ? null
                : $key;

        }

        throw new BadKeyException("Cant determine a key from this");

    }

    /**
     * Add a object to the chain task array if it is changed or new
     * Required for ChainSavingInterface
     *
     * @param array $task The existing array of tasks
     * @param string $key The string under which to add the object
     */
    public function chainSaving( array &$tasks, $key )
    {
        // don't forget isNew is tri-state
        if( $this->isNew() !== false or $this->isChanged() ) {
            $tasks[$key] = $this;
        }
    }

    /**
     * Has validation property?
     */
    public function canValidateProperty( $key )
    {
        return isset( $this->data[$key] ) and is_object( $this->data[$key] ) and $this->data[$key] instanceof Base;
    }

    /**
     * Has property?
     * @param mixed $key
     * @return bool
     */
    public function hasProperty( $key )
    {
        return \array_key_exists( $key, $this->data ) or in_array( $key, $this->additionalProperties );
    }

    // Array access functions
    public function offsetSet( $key, $value )
    {
        if( is_null( $key ) ) {
            return;
        }
        $this->set($key, $value);
    }

    public function offsetExists( $key )
    {
        return $this->issetProperty( $key );
    }

    public function offsetUnset( $key )
    {
        $this->unsetProperty( $key );
    }

    public function offsetGet( $key )
    {
        return $this->get($key);
    }

    /**
     * Iterator required functions
     */
    public function rewind()
    {
        reset( $this->data );
    }

    public function current()
    {
        return $this->get( key( $this->data ) );
    }

    public function key()
    {
        return key( $this->data );
    }

    public function next()
    {
        list($key,) = each( $this->data );
        return $this->get($key);
    }

    public function valid()
    {
        $key = key($this->data);
        return ( $key !== null && $key !== false );
    }

    /**
     * Implments \Countable
     */
    public function count()
    {
        return count( $this->data );
    }

}