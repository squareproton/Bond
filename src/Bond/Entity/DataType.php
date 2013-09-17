<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Entity;

use Bond\Pg\Catalog\PgClass;
use Bond\Pg\Catalog\PgAttribute;

use Bond\Exception\BadPropertyException;

use Bond\Sql\QuoteInterface;
use Bond\Sql\SqlInterface;
use Bond\Sql\Modifier;

/**
 * Description of DataType
 *
 * @author pete
 */
class DataType implements SqlInterface
{

    /**
     * Class constants
     */
    const PRIMARY_KEYS = 1;
    const FORM_CHOICE_TEXT = 2;
    const LINKS = 4;
    const REFERENCES = 8;
    const AUTOCOMPLETE = 16;

    /**
     * Name of this dataType. Think, column_name
     * @var string
     */
    protected $name;

    /**
     * Entity this dataType belongs to
     * @var string
     */
    protected $entity;

    /**
     * Full Qualified name of this dataType
     * @var string
     */
    protected $fullyQualifiedName;

    /**
     * The default properties that exist on all dataTypes. Values are only stored if they deviate from these.
     * WARNING. Changing this array will require all the entities to be rebuilt.
     * @var array
     */
    protected static $defaults = array(
        'type' => null,
        'isPrimaryKey' => false,
        'isUnique' => false,
        'isNullable' => false,
        'isArray' => false,
        'isFormChoiceText' => false,
        'isAutoComplete' => false,
        'isApiReadonly' => false,
        'isApiVisible' => true,
        'isInherited' => false,
        'length' => null,
        'default' => null,
    );

    /**
     * The datastore of column propeties
     * @var array
     */
    protected $data;

    /**
     * Standard constructor
     * @param string $name
     * @param array $data
     */
    public function __construct( $name, $entity, $fullyQualifiedName, $data = array() )
    {
        $this->name = $name;
        $this->entity = $entity;
        $this->fullyQualifiedName = $fullyQualifiedName;
        $this->data = array_merge( static::$defaults, $data );
    }

    /**
     * Property access here.
     * @param string $func
     * @param array $arguments
     * @return mixed
     */
    public function __call( $func, $arguments )
    {
        $property = null;
        switch( true ) {
            case substr( $func, 0, 2 ) === 'is':
                $property = $func;
                break;
            case substr( $func, 0, 3 ) === 'get':
                $property = lcfirst( substr( $func, 3 ) );
                break;
            default:
                throw new BadPropertyException( $func, $this, "Only `get` or `is`ser functions allowed on datatypes." );
        }
        return array_key_exists( $property, $this->data )
            ? $this->data[$property]
            : null
            ;
    }

    /**
     * Does this datatype link to a sequence
     * @param $sequenceName
     * @return bool
     */
    public function isSequence( &$name = null )
    {
        if( preg_match( "/^nextval\\('([^']+)'::regclass\\)$/", $this->data['default'], $matches ) ) {
            $name = $matches[1];
            return true;
        }
        $name = null;
        return false;
    }

    /**
     * Does this dataType reference a normality entity?
     * @param string $entityName
     * @return bool
     */
    public function isNormalityEntity( &$entity = null )
    {
        if( $this->getEntity() === 'normality' ){
            $entity = $this->data['normality'];
            return true;
        }
        $entity = null;
        return false;
    }

    /**
     * Does this DataType reference a Entity?
     * @param string $entityName
     * @return bool
     */
    public function isEntity( &$entity = null )
    {
        if( isset( $this->data['entity'] ) ) {
            $entity = $this->data['entity'];
            return true;
        }
        $entity = null;
        return false;
    }

    /**
     * Does this dataType reference a bool?
     * @param string $default
     * @return bool
     */
    public function isBool( &$default = null )
    {
        if( $this->data['type'] === 'bool' ) {
            $default = $this->data['default'];
            return true;
        }
        $default = false;
        return false;
    }

    /**
     * Does this dataType reference an int?
     * @param mixed $default
     * @return bool
     */
    public function isInt( &$default = null )
    {
        if( $this->data['type'] === 'int' ) {
            $default = $this->data['default'];
            return true;
        }
        $default = false;
        return false;
    }

    /**
     * Does this dataType reference an int?
     * @param mixed $default
     * @return bool
     */
    public function isFloat( &$default = null )
    {
        if( $this->data['type'] === 'float' ) {
            $default = $this->data['default'];
            return true;
        }
        $default = false;
        return false;
    }

    /**
     * Does this dataType reference a enum?
     * @param string $enumName
     * @return bool
     */
    public function isEnum( &$enumName = null )
    {
        if( isset( $this->data['enumName'] ) ){
            $enumName = $this->data['enumName'];
            return true;
        }
        $enumName = null;
        return false;
    }

    /**
     * Is this property unsettable?
     * @return bool
     */
    public function isUnsettable()
    {
        $default = $this->getDefault();
        if(
            // is primary key and got a default value
            ( $this->isPrimaryKey() and !empty( $default ) )

            // we might want to do something with createTimestamp and modifiedTimestamp here

        )
        {
            return true;
        }
        return false;
    }

    /**
     * Is this property a initialProperty. Ie, a property on a entity the Entity needs to maintain its initial state for.
     * @return bool
     */
    public function isInitialProperty()
    {
        return !$this->isUnsettable() and $this->isNormalityEntity();
    }

    /**
     * Does this dataType have a default value. If so what is it.
     * @return bool
     */
    public function hasDefault( &$default = null)
    {
        if( empty( $this->data['default']) ) {
            $default = null;
            return false;
        }
        $default = $this->data['default'];

        return true;
    }

    /**
     * This defaults to array()
     * @return array
     */
    public function getform()
    {
        if( isset( $this->data['form'] ) ) {
            return $this->data['form'];
        }
        return array();
    }

    /**
     * Standard __get
     * @param string $key
     * @return mixed
     */
    public function __get( $key )
    {
        switch( $key ) {
            case 'name':
            case 'entity':
            case 'fullyQualifiedName':
            case 'default':
            case 'data':
                return $this->$key;
        }
    }

    /**
     * Serialize a dataType
     * @return string
     */
    public function serialize()
    {
        $output = array();
        foreach( $this->data as $key => $value ) {
            if( !array_key_exists( $key, static::$defaults ) or static::$defaults[$key] !== $value ) {
                $output[$key] = $value;
            }
        }
        return json_encode(
            array(
                $this->name,
                $this->entity,
                $this->fullyQualifiedName,
                $output
            )
        );
    }

    /**
     * Unserialize a dataType
     * @param string $data
     * @return DataType
     */
    public static function unserialize( $data )
    {
        $data = json_decode( $data, true );
        return new static( $data[0], $data[1], $data[2], $data[3] );
    }

    /**
     * Generate a dataType from a Bond\Pg\Catalog\PgAttribute
     * @param Attribute $attribute
     * @return static
     */
    public static function makeFromAttribute( PgAttribute $attribute )
    {

        $name = $attribute->name;
        $entity = $attribute->getRelation()->getEntityName();
        $type = $attribute->getType();
        $fullyQualifiedName = $attribute->getFullyQualifiedName();

        $data = array(
            'isPrimaryKey' => $attribute->isPrimaryKey(),
            'isUnique' => $attribute->isUnique(),
            'isNullable' => !$attribute->notNull,
            'isArray' => $attribute->isArray,
            'isInherited' => $attribute->isInherited(),
            'type' => $type->getTypeQuery(),
            'length' => $attribute->length,
            'default' => $attribute->default,
        );

        $data += $attribute->getEntity();

        if( $type->isBool() and in_array( strtolower( $data['default'] ), array( 'true', 'false' ) ) ) {
            $data['default'] = \Bond\boolval( $data['default'] );
        }

        if( $type->isEnum() ) {
            $data['enumName'] = $type->name;
        }

        if( $tags = \Bond\extract_tags( $attribute->comment, 'form' ) ) {
            $data['form'] = $tags;
        }

        if( $tags = \Bond\extract_tags( $attribute->comment, 'api' ) ) {
            $data+= $tags;
        }

        if( $tags = \Bond\extract_tags( $attribute->comment, 'filter' ) ) {
            $data['filter'] = $tags;
        }

        if( $tags = \Bond\extract_tags( $attribute->comment, 'normality' ) ) {
            $data['isFormChoiceText'] = isset( $tags['form-choicetext'] );
            $data['isAutoComplete'] = isset( $tags['autoComplete'] );
        }

        return new static( $name, $entity, $fullyQualifiedName, $data );

    }

    /**
     * Generate a array of DataTypes from a Bond\Pg\Catalog\PgClass object
     * @param Bond\Pg\Catalog\PgRelation $relation
     * @return array
     */
    public static function makeFromRelation( PgClass $relation )
    {

        $output = array();

        foreach( $relation->getAttributes() as $attribute ) {
            $dataType = static::makeFromAttribute( $attribute );
            $output[$dataType->name] = $dataType;
        }

        return $output;

    }

    /**
     * Return a query tag for use in dynamic sql.
     *
     * @param string $name The name of the element. Key in db->query( ..., array( $name => ... ) );
     * @param array $dataType. A element of Entity\Base::dataTypes array. Generated by Bond\Normality\Entity::getTypesArray()
     *
     * @return string
     */
    public function toQueryTag( $cast = false )
    {
        return sprintf(
            '%%%s:%s%s%%',
            $this->name,
            isset($this->data['enumName']) ? 'text' : $this->data['type'],
            $this->data['isNullable'] ? '|null' : '',
            $cast ? 'cast' : ''
        );
    }

    /**
     * Cast datatype to a array that we operate on in javascript
     * @param array Keys we wish returned
     * @param array Keys returned
     */
    public function toJavascript( array $keys = array('type', 'isPrimaryKey', 'isUnique', 'isNullable', 'isArray') )
    {
        return array_intersect_key(
                $this->data,
                array_flip( $keys )
            ) +
            array(
                'name' => $this->name,
            );
    }

    /**
     * Return an array of arguments that when passed to Query::validate() validate a value
     *
     * @param array $dataType. A element of Entity\Base::dataTypes array. Generated by Bond\Normality\Entity::getTypesArray()
     * @param array $additionalModifiers
     *
     * @return array Array of arguments suitable for use by call_user_func_array
     */
    public function toQueryValidateArgs( array $modifiers = array() )
    {

        throw new \Bond\Exception\Depreciated("Use getQueryModifier()");

        if( isset( $modifiers['cast'] ) ) {
            // datatypes which aren't all lower case need to be identifier quoted
            if( strtolower( $this->data['type'] ) !== $this->data['type'] ) {
                $type = Query::quoteIdentifier( $this->data['type'] );
            } else {
                $type = $this->data['type'];
            }
            $modifiers['cast'] = $type;
        }

        if( $this->data['isNullable'] ) {
            $modifiers[] = 'null';
        }

        return array(
            $this->data['type'],
            false,
            $modifiers
        );
    }

    /**
     * Get a modifier customised to work on this datatype
     */
    public function getQueryModifier( QuoteInterface $quoting, $cast = true )
    {

        $modifier = new Modifier( $quoting, $this->data['type'], false );

        // nullify
        if( $this->data['isNullable'] ) {
            $modifier->add( 'pre', '\Bond\nullify' );
        }

        // datatypes which aren't all lower case need to be identifier quoted
        if( $cast ) {

            // datatypes which aren't all lower case need to be identifier quoted
            if( strtolower( $this->data['type'] ) !== $this->data['type'] ) {
                $type = $quoting->quoteIdent( $this->data['type'] );
            } else {
                $type = $this->data['type'];
            }

            $modifier->add( 'post', $modifier->generateCastClosure( $type ) );

        }

        return $modifier;

    }

    /**
     * @inheritDoc
     */
    public function parse( QuoteInterface $quoting )
    {
        return $quoting->quoteIdentifier( $this->name );
    }

}
