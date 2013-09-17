<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality\Builder;

use Bond\Normality\Builder\Exception\AliasCollisionException;
use Bond\Normality\Builder\Exception\FormChoiceTextConfigurationException;

use Bond\Normality\Php;
use Bond\Normality\PhpClass;
use Bond\Normality\PhpClassComponent\ClassConstant;
use Bond\Normality\PhpClassComponent\FunctionDeclaration;
use Bond\Normality\PhpClassComponent\VariableDeclaration;

use Bond\Pg\Catalog\PgClass;

use Bond\Entity\DataType;

use Bond\Format;
use Bond\MagicGetter;
use Bond\Normality\Sformatf;

/**
 * Entity generation
 *
 * @author pete
 */
class Entity implements BuilderInterface
{

    use MagicGetter;

    /**
     * Options array
     */
    private $options = array();

    /**
     * @var string
     */
    private $entityFileStore;

    /**
     * @var Bond\Pg\Catalog\PgClass
     */
    private $pgClass;

    /**
     * A copy of the $dataTypes array that will be end up in the repository
     * @var array
     */
    private $dataTypes;

    /**
     * References. See, Relation->getReferences()
     */
    private $references;

    /**
     * A copy of the 'self::$unsetableProperties'
     * @var array
     */
    private $unsetableProperties;

    /**
     * Additiional properties
     */
    private $additionalProperties = array();

    /**
     * A copy of the 'self::$lateLoadProperty' variable
     */
    private $lateLoadProperty;

    /**
     * A copy of the 'self::$isReadOnly' variable
     */
    private $isReadOnly;

    /**
     * A copy of the 'self::$keyProperties' array
     */
    private $keyProperties;

    /**
     * A array of alias' used in a __get or __set. Used to detect collisions.
     * @var array
     */
    private $getSetAliases = array();

    /**
     * Normality tags. Think \Bond\extract_tags( 'normality', $this->pgClass );
     * @var array
     */
    private $normalityTags;

    /**
     * Symfony2 form get compatible functions
     * @var array
     */
    private $symfonyFormGetters = array();

    /**
     * Symfony2 form get compatible functions
     * @var array
     */
    private $symfonyFormSetters = array();

    /**
     * @var Bond\Normality\PhpClass
     */
    private $class;

    /**
     * Standard constructor
     */
    public function __construct( PgClass $pgClass, array $options, $entityFileStoreBase = null )
    {

        $this->pgClass = $pgClass;
        $this->options = $options;
        $this->entityFileStore = $entityFileStoreBase. '/'. $this->pgClass->getEntityName();

        $this->class = new PhpClass( $this->pgClass->getEntityName(), $options['entity'], false );

        $this->references = $this->pgClass->getReferences();
        $this->normalityTags = $this->pgClass->getTags();
        $this->dataTypes = DataType::makeFromRelation( $this->pgClass );

        $this->loadUnsettableProperties();
        $this->loadLateLoadProperty();
        $this->loadKeyProperties();

        // create entity filestore directory structure.
        if( !is_dir( $this->entityFileStore ) ) {
            mkdir($this->entityFileStore, 0755, true);
        }

        // readonly property
        if( $this->pgClass->isLogTable() ) {
            $this->isReadOnly = 'READONLY_ON_PERSIST';
        }

        if( $this->pgClass->isView() or $this->pgClass->isMaterialisedView() ) {
            $this->isReadOnly = 'READONLY_EXCEPTION';
        }

        if( array_key_exists( 'isReadOnly', $this->normalityTags ) ) {
            $this->isReadOnly = $this->normalityTags['isReadOnly'];
        }

//         $this->class->classComponents[] = new ClassConstant(
//             'REPOSITORY_NAMESPACE',
//             new Sformatf( <<<'PHP'
//                     /**
//                      * Repository Location
//                      */
//                     const REPOSITORY_NAMESPACE = '%s';
// PHP
//                 , addslashes( $this->options['repository'] )
//             )
//         );

        $this->class->classComponents[] = new ClassConstant(
            'ENTITY_FILESTORE',
            new Sformatf( <<<'PHP'
                /**
                 * Entity FileStore directory
                 */
                const ENTITY_FILESTORE = '%s';
PHP
                , addslashes( $this->entityFileStore )
            )
        );

        $this->getClassExtends();
        $this->getGetSetFunction();
        $this->getSqlInterfaceFunction();
        $this->setClassComment();

        $this->getUnsettablePropertiesDeclaration();
        $this->getAdditionalPropertiesDeclaration();
        $this->getLateLoadPropertyDeclaration();
        $this->getKeyPropertiesDeclaration();
        $this->getIsReadOnlyPropertyDeclaration();
        $this->getDataDeclaration();
        $this->getIsZombieFunction();
        $this->getSymfonyloadValidatorMetadataFunction();

    }

    /**
     * Get class extends
     */
    protected function getClassExtends()
    {
        $this->class->addUses('Bond\Entity\Base');
        $this->class->addExtends('Base');
    }

    /**
     * Load unsettable properties into an array that can
     * be referenced elsewhere while building the entity
     * @return void
     */
    private function loadUnsettableProperties()
    {
        $this->unsetableProperties = array();
        foreach( $this->dataTypes as $name => $dataType ) {
            if( $dataType->isUnsettable() ) {
                $this->unsetableProperties[] = $name;
            }
        }
    }

    /**
     * Determine the lateLoadProperty
     * @return void
     */
    private function loadLateLoadProperty()
    {
        // can only lateLateLoad a property if it is the sole primary key
        $pks = $this->pgClass->getPrimaryKeys();

        $this->lateLoadProperty = ( count( $pks ) === 1 )
            ? $pks->pop()->name
            : null
            ;

    }

    /**
     * Load unsettable properties into an array that can
     * be referenced elsewhere while building the entity
     * @return void
     */
    private function loadKeyProperties()
    {
        $this->keyProperties = array();
        foreach( $this->dataTypes as $name => $dataType ) {
            if( $dataType->isPrimaryKey() ) {
                $this->keyProperties[] = $name;
            }
        }
    }

    /**
     * Entity generated Headers
     * @return array Headers
     */
    private function setClassComment()
    {

        $connectionSettings = $this->pgClass->getCatalog()->db->resource->connectionSettings;

        $header = new Sformatf( <<<TEXT
            ConnectionInfo %s@%s:%s,
            Relation %s
TEXT
            , $connectionSettings->dbname,
            $connectionSettings->user,
            $connectionSettings->host,
            $this->pgClass->getFullyQualifiedName()
        );

        $this->class->setClassComment( $header->docblockify() );

    }

    /**
     * Produce the static::$unsetablePropertiesArray
     * @return array
     */
    private function getUnsettablePropertiesDeclaration()
    {

        // if unsettable properties are the default we don't need to do anything
        if( $this->unsetableProperties === array('id') ) {
            return array();
        }

        $unsetableProperties = array_map(
            function($property){
                return "'".addslashes($property)."'";
            }
            , $this->unsetableProperties
        );

        $this->class->classComponents[] = new VariableDeclaration(
            'unsetableProperties',
            new Sformatf( <<<'PHP'
                /**
                 * Unsetable properties
                 * @var array
                 */
                protected static $unsetableProperties = [%s];
PHP
                , implode( ', ', $unsetableProperties )
            )
        );

        return $this;

    }

    /**
     * Produce the static::$additionalPropertiesArray
     * @return array
     */
    private function getAdditionalPropertiesDeclaration()
    {

        if( !$this->additionalProperties ) {
            return array();
        }

        $additionalProperties = array_map(
            function($property){
                return "'".addslashes($property)."'";
            }
            , $this->additionalProperties
        );

        $this->class->classComponents[] = new VariableDeclaration(
            'additionalProperties',
            new Sformatf( <<<'PHP'
                /**
                 * Additional properties
                 * @var array
                 */
                protected static $additionalProperties = [%s];
PHP
                , implode( ', ', $additionalProperties )
            )
        );

        return $this;

    }

    /**
     * Produce the static::$unsetablePropertiesArray
     * @return array
     */
    private function getLateLoadPropertyDeclaration()
    {

        $this->class->classComponents[] = new VariableDeclaration(
            'lateLoadProperty',
            new Sformatf( <<<'PHP'
                /**
                 * The property used for lateLoading
                 * @var string
                 */
                protected static $lateLoadProperty = %s;
PHP
                , Php::varExport( $this->lateLoadProperty )
            )
        );
        return $this;

    }

    /**
     * Produce the static::$isReadOnly property declaration
     * @return array
     */
    private function getIsReadOnlyPropertyDeclaration()
    {

        if( !isset( $this->isReadOnly ) ) {
            return $this;
        }

        if( !defined( "\Bond\Entity\Base::{$this->isReadOnly}" ) ) {
            throw new \InvalidArgumentException("normality.isReadOnly has bad value `{$this->isReadOnly}`.");
        }

        $this->class->classComponents[] = new VariableDeclaration(
            'isReadOnly',
            new Sformatf( <<<'PHP'
                /**
                 * The readonly property
                 * @var string
                 */
                protected static $isReadOnly = self::%s;
PHP
                , $this->isReadOnly
            )
        );
        return $this;

    }

    /**
     * Produce the static::$unsetablePropertiesArray
     * @return array
     */
    private function getKeyPropertiesDeclaration()
    {

        if( !$this->keyProperties ) {
            return $this;
        }

        $keyProperties = array_map(
            function($property){
                return "'".addslashes($property)."'";
            }
            , $this->keyProperties
        );

        $this->class->classComponents[] = new VariableDeclaration(
            'keyProperties',
            new Sformatf( <<<'PHP'
                /**
                 * Object properties which comprise this Entity's key.
                 * @var array
                 */
                protected static $keyProperties = [%s];
PHP
                , implode( ', ', $keyProperties )
            )
        );

        return $this;

    }

    /**
     * The $this->data() array declaration
     * return array $lines
     */
    private function getDataDeclaration()
    {

        $vars = $this->pgClass->getAttributes()->map(
            function ($column) {
                $dataType = $this->dataTypes[$column->name];

                // initial value
                $initialValue = null;

                // is bool with default
                if( $dataType->isBool( $default ) and is_bool( $default ) ) {
                    $initialValue = $dataType->getDefault();
                }

                $comment = '';

                // primary key
                if( $column->isPrimaryKey() ) {
                    $comment .= 'PK; ';
                }

                // references
                if( count( $references = $column->getReferences() ) ) {
                    $comment .= sprintf( "references %s", $references->implode(', ') );
                }

                // isReferencedBy
                if( count( $isReferencedBy = $column->getIsReferencedBy() ) ) {
                    $comment .= sprintf( "is referenced by %s", $isReferencedBy->implode(', ') );
                }

                // form options
                if( $formOptions = $column->getTags('form') ) {
                    $comment .= "Form options ".json_encode( $formOptions );
                }

                // explode, trim, implode comment
                $comment = implode( ", ", array_map( 'trim', explode( "\n", $comment ) ) );

                return sprintf(
                    "'%s' => %s,%s",
                    $column->name,
                    Php::varExport( $initialValue ),
                    $comment ? " # {$comment}" : ''
                );
            }
        );

        $vars = (new Format($vars))->indent(4);

        $this->class->classComponents[] = new VariableDeclaration(
            'data',
            new Sformatf( <<<'PHP'
/**
 * Columns defined in %s
 * @var array
 */
protected $data = array(
%s
);
PHP
                , $this->pgClass->getFullyQualifiedName()
                , $vars
            )
        );

        return $this;

    }

    /**
     * Produce the isZombie method
     */
    private function getIsZombieFunction()
    {

        // get primary key reference columns
        $zombieTests = $this->pgClass->getAttributes()->filter(
            function ($e) {
                return $e->isZombie();
            }
        )->map(
            function ($zombieCol) {
                return sprintf(
                    "( null === \$this->data['%s'] )",
                    addslashes( $zombieCol->name )
                );
            }
        );

        if( $zombieTests ) {

            $this->class->classComponents[] = new FunctionDeclaration(
                'data',
                new Sformatf( <<<'PHP'
                    /**
                     * Is a zombie object?
                     * @inheritDoc
                     */
                    public function isZombie()
                    {
                        return %s;
                    }
PHP
                    , implode( " or ", $zombieTests )
                )
            );
        }

    }

    /**
     * Produce the static entity function data
     * @return array $lines
     */
    private function getSqlInterfaceFunction()
    {

        if( count($this->keyProperties) !== 1 ) {

            $content = "    throw new \LogicException('Normality can\\'t (yet) handle tables without a primary key or multi-column primary keys.\\n".
                "Please extend the generator or overload {$this->pgClass->getEntityName()}->parse().');";

        } else {

            $primaryKey = $this->keyProperties[0];
            $dataType = $this->dataTypes[$primaryKey];

            // if datatype inheritied we don't need to do anything
            if( $dataType->isInherited() ) {
                return array();
            }

            // we're doing something
            $content = '';

            $this->class->addImplements( "SqlInterface");
            $this->class->addUses( 'Bond\Sql\QuoteInterface' );
            $this->class->addUses( 'Bond\Sql\SqlInterface' );

            if( $dataType->getType() === 'int' ) {

                // can this property be a entity?
                if( $dataType->isEntity() ) {
                    $content .= <<<PHP
    if( is_object( \$this->data['{$primaryKey}'] ) and \$this->data['{$primaryKey}'] instanceof SqlInterface ) {
        return \$this->data['{$primaryKey}']->parse( \$quoting );
    }\n
PHP
;
                }

                // cast integer datatype to int if not null
                $content .= <<<PHP
    return \$this->data['{$primaryKey}'] !== null ? (string) (int) \$this->data['{$primaryKey}'] : 'NULL';
PHP
;

            } else {

                $content .= <<<PHP
    return \$quoting->quote( \$this->data['{$primaryKey}'] );
PHP
;

            }

        }

        $fn = new Sformatf( <<<'PHP'
/**
 * Impementation of interface \Bond\Pg\Query->Validate()
 * @return scalar
 */
public function parse( QuoteInterface $quoting )
{
%s
}

PHP
            , $content
        );

        $this->class->classComponents[] = new FunctionDeclaration( "parse", $fn );

    }

   /**
    * BEGIN BLOCK - generation of
    *
    * function get()
    * function set()
    * function get_{$propertyname}
    * function set_{$propertyname}
    */

    /**
     * The entity->get() function declaration.
     *
     * @return array Lines
     */
    private function getGetSetFunction()
    {

        $get = array();
        $set = array();

        // iterate over the types
        foreach( $this->dataTypes as $columnName => $type ) {

            $property = $this->getSetAlias( $columnName, 'data' );

            // Don't need to include inheritied columns here because the parent class definitions should catch them
            if( $type->isInherited() ) {
                continue;
            }

            // DEPRECIATED Symfony2 compatible getter / setter
            $this->addSymfonyFormCompatibleGetter( $property );
            $this->addSymfonyFormCompatibleSetter( $property );

            // types
            switch (true) {

                case $type->getEntity() === 'normality':

                    $this->getsetEntity( $get, $set, $property, $type->getNormality() );
                    break;

                case $type->getEntity():

                    $this->generateCallbacksEntityStaticMethods( $property, $type->getEntity() );
                    break;

                case $type->getType() === 'bool':

                    $this->generateGetSetBoolCallback( $get, $set, $property );
                    break;

            }

        }

        // links
        foreach( $this->pgClass->getLinks() as $name => $link ) {

            $this->generateGetSetLinksCallback( $get, $set, $name, $link );

        }

        // referenced by (if we've got any)
        foreach( $this->references as $reference ) {

            $this->generateGetSetReferenceCallback( $get, $set, $reference );

        }

        $this->formatGetFunctionDeclaration( $get );
        $this->formatSetFunctionDeclaration( $set );

        return $this;

    }

    private function generateCallbacksEntityStaticMethods( $columnName, $entity )
    {

        $this->class->addUses( 'Bond\\Entity\\StaticMethods' );

        // some of the functions require the entity as a third argument
        if( in_array( $entity, ['PgLargeObject'] ) ) {
            $additionalArguments = ' , $this';
        } else {
            $additionalArguments = '';
        }

        $get = <<<'PHP'
            /**
             * 'get' callback for $this->data['%1$s']
             * @param mixed $value
             * @return %2$s
             */
            protected function get_%1$s( &$value )
            {
                return StaticMethods::get_%2$s( $value%3$s );
            }
PHP
;

        $set = <<<'PHP'
            /**
             * 'set' callback for $this->data['%1$s']
             * @param mixed $value
             * @return %2$s
             */
            protected function set_%1$s( $value, $inputValidate )
            {
                return StaticMethods::set_%2$s( $value, $inputValidate%3$s );
            }
PHP
;

        $this->class->classComponents[] = new FunctionDeclaration(
            "getset.{$columnName}.get",
            new Sformatf(
                $get,
                $columnName,
                $entity,
                $additionalArguments
            )
        );

        $this->class->classComponents[] = new FunctionDeclaration(
            "getset.{$columnName}.set",
            new Sformatf(
                $set,
                $columnName,
                $entity,
                $additionalArguments
            )
        );

    }

    /**
     * many to many references to foreign entities
     *
     * @param array $get
     * @param array $set
     * @param $reference See, Relation->getLinks()
     */
    private function generateGetSetLinksCallback( &$get, &$set, $name, $link )
    {

        foreach( $link->foreignEntities as $foreignEntity ) {

            $nameForeignEntity = $this->getSetAlias( "{$foreignEntity}s", 'data' );

            $this->additionalProperties[] = $nameForeignEntity;

            $this->class->addUses('Bond\\Repository');
            $this->class->addUses( 'Bond\\Entity\\StaticMethods' );

            // we got a ranking column?
            $sort = '';
            if( isset( $link->linkSortColumn ) ) {
                $sort = sprintf(
                    "->sortByProperty('%s')",
                    addslashes( $link->linkSortColumn )
                );
            }

            // get
            $getCaseStatement = explode( "\n", sprintf( <<<CASE
case '%s':
    return Repository::linksGet( \$this, '%s', '%s', null, true )%s;\n
CASE
                ,
                $nameForeignEntity,
                $name,
                $foreignEntity,
                $sort
            ));

            $get = array_merge( $get, $getCaseStatement );

            // set
            $setCaseStatement = explode( "\n", sprintf( <<<CASE
case '%s':
    return StaticMethods::set_links( \$this, '%s', \$value );\n
CASE
                ,
                $nameForeignEntity,
                $name
            ));

            $set = array_merge( $set, $setCaseStatement );

        }

    }

    /**
     * References to foreign entities in a 1->1 or 1->many way.
     * Think, Contact -> ContactUser or Contact -> Addresses
     *
     * @param array $get
     * @param array $set
     * @param $reference See, Relation->getReferences()
     */
    private function generateGetSetReferenceCallback( &$get, &$set, $reference )
    {

        // we not on the ignore list?
        if( !( $property = $this->getSetAlias( $reference, 'reference' ) ) ) {
            return;
        }

        $this->additionalProperties[] = $property;

        // DEPRECIATED Symfony2 compatible getter / setter
        // $this->addSymfonyFormCompatibleGetter( $property );
        // $this->addSymfonyFormCompatibleSetter( $property );

        $this->class->addUses('Bond\\Repository');
        $this->class->addUses( 'Bond\\Entity\\StaticMethods' );

        // get
        $getCaseStatement = explode( "\n", sprintf( <<<CASE
case '%s':
    return \$this->r()->referencesGet( \$this, '%s.%s' );\n
CASE
            ,
            $property,
            $reference[0],
            $reference[1]
        ));

        $get = array_merge( $get, $getCaseStatement );

        // set
        $setCaseStatement = explode( "\n", sprintf( <<<CASE
case '%s':
    return StaticMethods::set_references( \$this, '%s.%s', \$value );\n
CASE
            ,
            $property,
            $reference[0],
            $reference[1]
        ));

        $set = array_merge( $set, $setCaseStatement );

    }

    /**
     * Bool columns.
     *
     * @param array $get
     * @param array $set
     * @param string $columnName
     * @param string $entity
     */
    private function generateGetSetBoolCallback( &$get, &$set, $columnName )
    {

        $this->class->classComponents[] = new FunctionDeclaration(
            "getset.{$columnName}.get",
            $this->generateGetCallbackForBool( $columnName )
        );

        $this->class->classComponents[] = new FunctionDeclaration(
            "getset.{$columnName}.set",
            $this->generateSetCallbackForBool( $columnName )
        );

    }

    /**
     * We've found a column that needs a entry in get() and set(). Build the case statements for this.
     *
     * @param array $get
     * @param array $set
     * @param string $columnName
     * @param string $entity
     */
    private function getSetEntity( &$get, &$set, $columnName, $entity )
    {

        foreach( array( 'get', 'set' ) as $type ) {

            // get case statement
            $caseStatement = explode( "\n", sprintf( <<<CASE
case '%s':
    \$key = '%s';
    break;\n
CASE
                ,
                $entity,
                $columnName
            ));
            $$type = array_merge( $$type, $caseStatement );

            // get callback

            $this->class->classComponents[] = new FunctionDeclaration(
                "getset.{$columnName}.{$type}",
                call_user_func( "self::generate{$type}CallbackForEntity", $columnName, $entity )
            );

        }

    }

    /**
     * Generate 'setter' callback for entities
     * @param string $columnName
     * @param string $entity
     */
    private function generateSetCallbackForEntity( $columnName, $entity )
    {

        return new Sformatf( <<<'PHP'
            /**
             * 'set' callback for $this->data['%1$s']
             * @param mixed $value
             * @return %2$s
             */
            protected function set_%1$s( $value )
            {
                if( $value instanceof %3$s ) {
                    return $value;
                } elseif( is_scalar( $value ) ) {
                    return $this->entityManager->find('%3$s', $value);
                    // return %3$s::r()->find( $value ); // old stylee
                } elseif( is_array( $value ) ) {
                    $entity = $this->get('%1$s');
                    if( !$entity ) {
                        return $this->entityManager->make('%3$s');
                        // $entity = %3$s::r()->make(); // old stylee
                    }
                    $entity->set( $value, null, self::VALIDATE_STRIP );
                    return $entity;
                }
                return null;
            }
PHP
            , $columnName
            , $entity
            , "\\{$this->options['entityPlaceholder']}\\{$entity}"
        );

    }

    /**
     * Generate 'getter' callback for entities
     * @param string $columnName
     * @param string $entity
     */
    private function generateGetCallbackForEntity( $columnName, $entity )
    {

        return new Sformatf( <<<'PHP'
            /**
             * 'get' callback for $this->data['%1$s']
             * @param scalar
             * @return %2$s
             */
            protected function get_%1$s( &$value )
            {
                if( !is_object( $value ) ) {
                    $value = $this->entityManager->find( '%2$s', $value );
                }
                return $value;
            }
PHP
            , $columnName
            , "\\{$this->options['entityPlaceholder']}\\{$entity}"
        );

    }

    /**
     * Generate 'setter' callback for bool's
     * @param string $columnName
     */
    private function generateSetCallbackForBool( $columnName )
    {

        return new Sformatf( <<<'PHP'
            /**
             * 'set' callback for $this->data['%1$s']
             * @param mixed $value
             * @return bool
             */
            protected function set_%1$s( $value )
            {
                return $value;
            }
PHP
            , $columnName
        );

    }

    /**
     * Generate 'getter' callback for bools
     * @param string $columnName
     * @param string $entity
     */
    private function generateGetCallbackForBool( $columnName )
    {

        return new Sformatf( <<<'PHP'
            /**
             * 'get' callback for $this->data['%1$s']
             * @param mixed
             * @return bool
             */
            protected function get_%1$s( &$value )
            {
                return \Bond\boolval( $value );
            }
PHP
            , $columnName
        );

    }

    /**
     * Build function declaration get()
     * @param array $lines Array of lines comprimising 'case:' (switch stylee) statements that form the bassis for function get(...)
     * @return string
     */
    private function formatGetFunctionDeclaration( $lines )
    {

        // do we need a switch statement
        $switch = '';
        if( count( $lines ) ) {
            $lines = new Format($lines);
            $switch = <<<PHP
    switch( \$key ) {

{$lines->indent(8)}
    }\n
PHP;
        }

        $fn = new Sformatf(
            <<<'PHP'
/**
 * Get entity property
 * {@inheritDoc}
 */
public function get( $key, $inputValidate = null, $source = null, \Bond\RecordManager\Task $task = null )
{
%s    return parent::get( $key, $inputValidate, $source, $task );
}
PHP
            , $switch
        );

        $this->class->classComponents[] = new FunctionDeclaration( "get", $fn );

    }

    /**
     * Build function declaration set()
     * @param array $lines Array of lines comprimising 'case:' (switch stylee) statements that form the bassis for function set(...)
     */
    private function formatSetFunctionDeclaration( $lines )
    {

        // do we need a switch statement
        $switch = '';
        if( count( $lines ) ) {
            $lines = new Format($lines);
            $switch = <<<PHP
    switch( \$key ) {

{$lines->indent(8)}
    }\n
PHP;
        }

        $fn = new Sformatf(
            <<<'PHP'
/**
 * Set entity property
 * {@inheritDoc}
 */
public function set( $key, $value = null, $inputValidate = null )
{
%s    return parent::set( $key, $value, $inputValidate );
}

PHP
            , $switch
        );

        $this->class->classComponents[] = new FunctionDeclaration( "set", $fn );

    }

    /**
     * GetSetAlias. Use to change the system default property names (which might not be overly intuitive) into something more usage.
     *
     * See, @normality.alias[address.contactId]: Addresses
     * Detects collisions.
     *
     * @param data $data
     * @return string $alias
     */
    private function getSetAlias( $data, $type )
    {

        // build name
        switch( $type ) {

            case 'reference':

                $name = sprintf(
                    '%s%s',
                    $data[0],
                    $data[2] ? 's' : ''
                );

                $key = "{$data[0]}.{$data[1]}";

                if( isset( $this->normalityTags['alias']['reference'][$key] ) ) {
                    $name = $this->normalityTags['alias']['reference'][$key];
                }
                break;

            case 'data':

                $name = $data;
                break;

            default:
                throw new \InvalidArgumentException("Unknown type for getSetAlias");

        }

        // are we going to ignore this name
        if( strtolower( $name ) === '__ignore' ) {
            return null;
        }

        if( in_array( $name, $this->getSetAliases ) ) {
            $message = "Collision on entity {$this->pgClass->getEntityName()} for property {$name}. Don't worry about this.\n";
            echo $message;
            # throw new AliasCollisionException();
        }
        $this->getSetAliases[] = $name;

        return $name;

    }

    /**
     * Build normality compatible getters and setters
     */
    private function getSymfonyCompatibleGettersAndSetters()
    {

        return array();

        if( !$this->symfonyFormGetters ) {
            return array();
        }

        // build output
        $output = explode( "\n", <<<PHP
/**
* Symfony2 compatible getters and setters.
* These are __not__ to be used by Bond code. These have be depreciated.
*/
// /*
PHP
        );

        $output = array_merge(
            $output,
            $this->symfonyFormGetters,
            array(''),
            $this->symfonyFormSetters,
            array('/**/')
        );

        return $output;

    }

    /**
     * Build a symfony2 form compatible getter
     * @param propertyName $name
     */
    private function addSymfonyFormCompatibleGetter( $name )
    {

        $fnName = "get".\Bond\pascal_case($name);
        $fnBody = sprintf(
            "function %s() { return \$this->get('%s'); }",
            $fnName,
            addslashes( $name )
        );

        $this->class->classComponents[] = new FunctionDeclaration( $fnName, $fnBody );

    }

    /**
     * Build a symfony2 form compatible setter
     * @param propertyName $name
     */
    private function addSymfonyFormCompatibleSetter( $name )
    {

        $fnName = "set".\Bond\pascal_case($name);
        $fnBody = sprintf(
            "function %s(\$value) { return \$this->set('%s',\$value); }",
            $fnName,
            addslashes( $name )
        );

        $this->class->classComponents[] = new FunctionDeclaration( $fnName, $fnBody );

    }

    /**
     * Form choice text function
     * @return array $lines
     */
    private function getFormChoiceTextFunction()
    {

        // default function only works for tables with a lone primary key
        $columns = $this->pgClass->getAttributes();

        // find columns tagged 'form-choicetext'
        $columnsSelected = array();
        foreach( $columns as $column) {
            $tags = \Bond\extract_tags( $column, 'normality' );
            if( isset( $tags['form-choicetext']) ) {
                $columnsSelected[] = $column;
            }
        }

        switch( count($columnsSelected) ) {
            case 0:
                return;
            case 1:
                $columnName = array_pop( $columnsSelected )->name;
                break;
            default:
                throw new \FormChoiceTextConfigurationException( "Multiple columns with tag 'form-choicetext'. Only one column per table can have this tag." );
        }

        $this->class->classComponents[] = new FunctionDeclaration(
            'form_choiceText',
            new Sformatf( <<<'PHP'
                /**
                * Function called by \Bond\Bridge\Normality\Form\Type\Normality when displaying text for this entity
                * @return scalar
                */
                public function form_choiceText()
                {
                    return $this->get('%s');
                }
PHP
                , addslashes( $columnName )
            )
        );

    }

    /**
     * Generate the Symfony loadValidatorMetadata method.
     * @return array
     */
    private function getSymfonyloadValidatorMetadataFunction()
    {

        $this->class->addUses( 'Symfony\Component\Validator\Mapping\ClassMetadata' );

        $this->class->classComponents[] = new FunctionDeclaration(
            '_loadValidatorMetadata',
            new Sformatf( <<<'PHP'
/**
* Symfony Validator Metadata.
*
* @param ClassMetadata $metadata
* @return void
*/
protected static function _loadValidatorMetadata( ClassMetadata $metadata )
{
%s

}
PHP
                , $this->getSymfonyValidatorConstraints()
            )
        );

    }

    /**
     * Get Symfony validator contraints
     * @return string
     */
    private function getSymfonyValidatorConstraints()
    {

        $constraints = array();

        $this->loadUnsettableProperties();

        foreach ( $this->dataTypes as $column => $type ) {

            // skip inherited columns
            if( $type->isInherited() ) {
                continue;
            }

            // Skip Columns designated unsetable.
            if( in_array( $column, $this->unsetableProperties ) ) {
                continue;
            }

            // Check if column is nullable.
            if( !$type->isNullable() and !$type->hasDefault() ){
                $constraints[] = $this->getSymfonyConstraint( $column, 'NotNull');
            }

            // Normality entity type constraints.
            if( $type->getEntity() === 'normality' ) {

                $dataType = $this->options['entityPlaceholder']."\\{$type->getNormality()}";
                $constraints[] = $this->getSymfonyTypeConstraint( $column, $dataType );

            } elseif( $type->getEntity() ){

                $dataType = "Bond\\Entity\\Types\\{$type->getEntity()}";
                $constraints[] = $this->getSymfonyTypeConstraint( $column, $dataType );

            } elseif( $type->isEnum( $enumName ) ) {

                $constraints[] = $this->getSymfonyChoiceConstraint(
                    $column,
                    $this->pgClass->getCatalog()->enum->getValues($enumName)
                );

            } else {

                switch( $type->getType() ) {

                    case 'int':

                        $constraints[] = $this->getSymfonyConstraint( $column, 'RealInt', null, false );
                        $this->class->addUses( 'Bond\\Bridge\\Normality\\Constraint\\RealInt' );
                        break;

                    case 'citext':
                    case 'text':

                        $constraints[] = $this->getSymfonyTypeConstraint( $column, 'string' );
                        if( is_numeric( $type->getLength() ) ) {
                            $constraints[] = $this->getSymfonyConstraint( $column, 'MaxLength', $type->getLength() );
                        }
                        break;

                    case 'bool':

                        $constraints[] = $this->getSymfonyTypeConstraint( $column, 'bool' );
                        break;

                }

            }

            $constraints[] = '';

        }

        return implode( "\n", $constraints );

    }

    /**
    * Symfony Simple named constraint with 1 single / no arguments, i.e.
    * NotBlank, Blank, Null, NotNull, MinLength, MaxLength, etc.
    * @param mixed $column
    * @param mixed $contraint
    * @return mixed
    */
    private function getSymfonyConstraint( $column, $contraint, $args = null, $addUses = true )
    {

        if( $addUses ) {
            $this->class->addUses(
                sprintf(
                    'Symfony\\Component\\Validator\\Constraints\\%s',
                    $contraint
                )
            );
        }

        if( is_null( $args ) ){

            $output = <<<PHP
\$metadata->addPropertyConstraint(
    '%s',
    new %s()
);
PHP
;

        } else {

            $output = <<<PHP
\$metadata->addPropertyConstraint(
    '%s',
    new %s(
        %s
    )
);
PHP
;

        }

        return (
            new Sformatf(
                $output,
                addslashes($column),
                $contraint,
                Php::varExport($args, 8, true)
            )
        )->indent(4);

    }

    /**
    * Symfony Type Constraint generator
    * @param mixed $column
    * @param mixed $type
    */
    private function getSymfonyTypeConstraint( $column, $type )
    {

        return $this->getSymfonyConstraint( $column, 'Type', array(
            'type' => $type
        ));

    }

    /**
    * Symfony Choice Constraint generator
    * @param mixed $column
    * @param mixed $enumOptions
    */
    private function getSymfonyChoiceConstraint( $column, $enumOptions )
    {
        return $this->getSymfonyConstraint(
            $column,
            'Choice',
            array(
                'choices' => $enumOptions
            )
        );
    }

}