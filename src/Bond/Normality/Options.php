<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality;

use Bond\Exception\BadPathException;
use Bond\Exception\MissingOptionException;
use Bond\Exception\UnknownOptionException;
use Bond\Exception\UnknownPropertyForMagicGetterException;
use Bond\Exception\UnknownPropertyForMagicSetterException;

use Bond\Pg\Catalog;
use Bond\Pg\Catalog\PgClass;
use Bond\Flock;
use Bond\MagicGetter;

class Options
{

    use MagicGetter;

    /**
     * CLASS CONSTANTS
     */
    const REBUILD = 1;
    const REBUILD_NO = 2;
    const REBUILD_DEFAULT = 2;

    const BACKUP = 4;
    const BACKUP_NO = 8;
    const BACKUP_DEFAULT = 4;

    const REMOVE_ORPHANS = 16;
    const REMOVE_ORPHANS_NO = 32;
    const REMOVE_ORPHANS_DEFAULT = 32;

    private $regenerateEntityPlaceholders = false;

    private $regenerateRepositoryPlaceholders = false;

    private $simulate = true;

    private $matches;

    private $prepareOptions = 0;

    private $catalog;

    private $pathRoot;

    /**
     * The location for our entity quartet
     * @var array
     */
    private $paths = array(
        'entity' => null,
        'entityPlaceholder' => null,
        'repository' => null,
        'repositoryPlaceholder' => null,
        'log' => null,
        'register' => null,
        'backup' => null,
        'entityFileStore' => null,
    );

    public function __construct( Catalog $catalog, $pathRoot )
    {
        $this->catalog = $catalog;
        $this->matches = new Flock( 'Bond\Normality\MatchRelation\MatchRelationInterface' );

        $path = realpath( $pathRoot );
        if( !$path or !is_dir($path) ) {
            throw new BadPathException( $value );
        }
        $this->pathRoot = $path;
    }

    public function __set( $key, $value )
    {
        switch( $key ) {
            case 'regenerateEntityPlaceholders':
            case 'regenerateRepositoryPlaceholders':
            case 'simulate':
                $this->$key = (bool) $value;
                return;
            case 'prepareOptions':
                $this->prepareOptions = (int) $value;
                return;
        }
        throw new UnknownPropertyForMagicSetterException($this, $key);
    }

    public function __get( $key )
    {
        // options
        if( $key === 'prepareOptions' ) {
            $options = $this->prepareOptions;
            $options += $options & ( self::REMOVE_ORPHANS | self::REMOVE_ORPHANS_NO ) ? 0 : self::REMOVE_ORPHANS_DEFAULT;
            $options += $options & ( self::BACKUP | self::BACKUP_NO ) ? 0 : self::BACKUP_DEFAULT;
            $options += $options & ( self::REBUILD | self::REBUILD_NO ) ? 0 : self::REBUILD_DEFAULT;
            return $options;
        // namespaces
        } elseif( $key === 'namespaces' ) {
            $namespaces = [];
            foreach( $this->paths as $name => $value ) {
                $namespaces[$name] = $this->getNamespace( $name );
            }
            return $namespaces;
        // everything else
        } elseif ( property_exists( $this, $key ) ) {
            return $this->$key;
        }
        throw new UnknownPropertyForMagicGetterException( $this, $key );
    }

    public function getPath( $key )
    {
        if( !$this->paths[$key] ) {
            throw new \LogicException("Path `{$key}` not set.");
        }
        return $this->paths[$key];
    }

    public function setPath( $path, $value = null, $createIfNotExists = true )
    {
        if( is_array($path) ) {
            foreach( $path as $_path => $_value) {
                $this->setPath($_path, $_value, $createIfNotExists);
            }
            return;
        }
        if( !array_key_exists($path, $this->paths) ) {
            throw new UnknownOptionException( $path, array_keys($this->paths) );
        }

        // create
        $fullPath = $this->pathRoot . $value;
        if( !file_exists($fullPath) and $createIfNotExists ) {
            mkdir( $fullPath, 0775, true );
        } else {
            if( !is_dir( $fullPath ) ) {
                throw new BadPathException( $fullPath );
            }
        }
        $this->paths[$path] = $fullPath;
    }

    public function getMatchingRelations( &$skipping )
    {

        $processing = $this->catalog->pgClasses->newEmptyContainer();
        $skipping = $this->catalog->pgClasses->newEmptyContainer();

        // get all the relations in the database
        $relations = $this->catalog->pgClasses
            ->findByRelkind( array('r', 'v') )
            ->removeBySchema( 'dev' )
            ->removeBySchema( 'import' )
            ;

        foreach( $relations as $relation ) {

            if( $this->matches($relation) ) {
                $processing->add( $relation );
            } else {
                $skipping->add( $relation );
            }

        }

        return $processing;

    }

    public function getNamespace( $key )
    {
        if( !array_key_exists( $key, $this->paths ) ) {
            throw new UnknownOptionException( $key, array_keys($this->paths) );
        }
        if( !$this->paths[$key] ) {
            throw new \LogicException( "Path not yet set for `{$key}`" );
        }
        $nameSpace = substr( $this->paths[$key], strlen($this->pathRoot) );
        return str_replace('/', '\\', ltrim( $nameSpace, '/' ) );
    }

    public function checkPaths()
    {
        $missing = [];
        foreach( $this->paths as $key => $value ) {
            if( !$value ) {
                $missing[] = $key;
            }
        }
        if( $missing ) {
            throw new MissingOptionException($missing);
        }
        return true;
    }

    public function getPathsToBackup()
    {
        $pathsToBackup = $this->paths;
        unset( $pathsToBackup['log'], $pathsToBackup['backup'] );

        // don't include a directory if it is wholely included in another
        sort( $pathsToBackup, SORT_NATURAL );

        $output = array_reduce(
            $pathsToBackup,
            function( &$result, $working ) {
                $last = current($result);
                if( !$last || 0 !== strpos( $working, $last."/" ) ) {
                    $result[] = $working;
                    next($result);
                }
                return $result;
            },
            []
        );

        return $output;
    }

    public function matches( PgClass $relation )
    {
        return $this->matches->every(
            function( $check ) use ($relation) {
                return $check( $relation );
            }
        );
    }

}