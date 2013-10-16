<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond;

use Bond\Normality\Builder\Entity;
use Bond\Normality\Builder\EntityChild;
use Bond\Normality\Builder\EntityManager as EntityManagerBuilder;
use Bond\Normality\Builder\EntityManagerPgTypeConversion;
use Bond\Normality\Builder\EntityPlaceholder;
use Bond\Normality\Builder\PgRecordConverter;
use Bond\Normality\Builder\Repository as RepositoryG;
use Bond\Normality\Builder\RepositoryPlaceholder;

use Bond\Container;

use Bond\Entity\Types\DateTime;
use Bond\Flock;
use Bond\MagicGetter;

use Bond\Normality\Options;
use Bond\Pg\Catalog\PgClass;

use Bond\Profiler;

/**
 * Normality. The entity generator for Postgres done right.
 * All round auto code generation fiend.
 *
 * @author pete
 */
class Normality
{

    use MagicGetter;

    /**
     * Benchmarking information
     */
    private $profiler;

    /**
     * Normality options
     */
    private $options;

    /**
     * Entity manager
     * @var Bond\EntityManager
     */
    private $entityManager;

    /**
     * Callbacks
     */
    private $callbacks;

    /**
     * Relation matcher
     */
    private $matches;

    /**
     * Standard constructor
     */
    public function __construct( Options $options, EntityManager $entityManager = null, array $callbacks = array() )
    {

        $options->checkPaths();

        $this->options = $options;
        $this->entityManager = $entityManager;

        $this->callbacks = new Flock('is_callable');
        $this->callbacks['log'] = function(){};
        $this->callbacks->merge( $callbacks );

        $this->processed = new Container();

        $this->matches = new Flock('Bond\Normality\MatchRelationInterface');

        $this->profiler = new Profiler( "Normality Entity generation" );
        $this->profiler->log("Init");

    }

    /**
     * Build all entities
     * @return int Number of jobs completed
     */
    public function build()
    {

        $this->profiler->log('Build started');
        $this->callbacks['log']( "normality entity builder start" );

        $this->prepare();

        $processing = $this->options->getMatchingRelations( $skipping );

        $this->callbacks['log']( "skipping {$skipping->count()} ". $skipping->implode(', ', 'name' ) );
        $this->callbacks['log']( "processing {$processing->count()}" );

        // build relation
        $this->profiler->log('generation begin');
        $built = [];
        $pgRecordConverters = [];
        foreach( $processing as $relation ) {
            $output = $this->buildRelation( $relation );
            $entity = $output[0];
            $pgRecordConverters[] = $output[1];
            $this->profiler->log($entity);
            $built[] = $entity;
        }
        $this->profiler->log('generation end');

        $this->buildEntityRegistration( $built, $pgRecordConverters );

        // remove orphan entities
        $options = $this->options->prepareOptions;
        if( $options & Options::REMOVE_ORPHANS ) {

            if( !( $options & Options::BACKUP ) ) {
                throw new IncompatibleOptionException("You cannot have Options::REMOVE_ORPHANS set if Options::BACKUP is not set");
            }

            $dirs = [
                $this->options->getPath('entity'),
                $this->options->getPath('entityPlaceholder'),
                $this->options->getPath('repository'),
                $this->options->getPath('repositoryPlaceholder'),
            ];

            $files = [];

            foreach( $dirs as $dir ) {

                $i = new \DirectoryIterator($dir);

                foreach( $i as $file ) {
                    if( !$file->isDot() and !$file->isDir() ) {
                        $files[$file->getPathname()] = substr( $file->getBasename(), 0, -4 );
                    }
                }

            }

            // identify files we no longer need
            $orphans = array_diff( $files, $built );

            foreach( $orphans as $orphan => $entityName ) {
                unlink( $orphan );
            }

        }

        return $processing;

    }

    /**
     * Prepare the way for a new build.
     * Optionally backup/trash any existing entities
     * @return null
     */
    private function prepare()
    {

        $options = $this->options->prepareOptions;

        if( $options & Options::BACKUP or true ) {

            $root = $this->options->pathRoot;
            $pathsToBackup = $this->options->getPathsToBackup();

            // make a fully qualified path relative to $root
            $anchorToRoot = function( $path ) use ($root) {
                return escapeshellarg( "." . substr( $path, strlen($root) ) );
            };

            $pathsToBackup = array_map( $anchorToRoot, $pathsToBackup );
            $pathsToBackup = implode( ' ', $pathsToBackup );

            $backupDir = $this->options->getPath('backup');
            $filename = "{$backupDir}/" . self::fileSystemFriendly(
                date( DateTime::POSTGRES_TIMESTAMP_WITHOUT_TIME_ZONE_NO_MICROSECONDS )
            ) . '.tar.gz';

            $command = sprintf(
                'cd %s; tar cszf %s --exclude=%s -C %s %s; cd -',
                escapeshellarg( $root ),
                escapeshellarg( $filename ),
                $anchorToRoot( $backupDir ),
                escapeshellarg( $root ),
                $pathsToBackup
            );

            exec( $command );

        }

        // trash _all_ previously generated entitys
        /* BROKEN - Too agressive. Doesn't consider $options['match']
        $rebuild = in_array( $option, array( self::REBUILD_AND_BACKUP, self::REBUILD_NO_BACKUP ) );
        if( $rebuild ) {
            foreach( new \DirectoryIterator( $this->paths['entity'] ) as $item ) {
                if( $item->isFile() ) {
                    // unlink( $item->getPathname() );
                }
            }
        }
         */

    }

    private function generate( $generator, $overwrite )
    {

        if( $generator instanceof Entity ) {
            $type = 'entity';
        } elseif ( $generator instanceof EntityPlaceholder ) {
            $type = 'entityPlaceholder';
        } elseif ( $generator instanceof RepositoryG ) {
            $type = 'repository';
        } elseif ( $generator instanceof RepositoryPlaceholder ) {
            $type = 'repositoryPlaceholder';
        } elseif ( $generator instanceof PgRecordConverter ) {
            $type = 'pgRecordConverter';
        } else {
            throw new \Exception("Nope");
        }

        $fileName = sprintf(
            "%s/%s.php",
            $this->options->getPath($type),
            $generator->class->class
        );

        // replacing?
        if( file_exists($fileName) ) {
            if( $overwrite ) {
                unlink( $fileName );
            } else {
                $this->callbacks['log']('.', false);
                return false;
            }
        }

        file_put_contents( $fileName, $generator->class->render() );

        $this->callbacks['log']('#', false);

    }

    private function buildRelation( PgClass $relation )
    {

        $name = $relation->getEntityName();

        /// logging
        $this->callbacks['log']("{$name} ", false);
        $this->profiler->log($name);

        // construct generators
        if( $relation->isInherited() ) {
            $entityBuilderClass = EntityChild::class;
        } else {
            $entityBuilderClass = Entity::class;
        }

        $entityG = new $entityBuilderClass( $relation, $this->options->namespaces, $this->options->getPath('entityFileStore') );

        $entityPlaceholderG = new EntityPlaceholder( $entityG, $this->options->getNamespace('entityPlaceholder') );
        $repositoryG = new RepositoryG( $entityG, $this->options->getNamespace('repository') );
        $repositoryPlaceholderG = new RepositoryPlaceholder( $repositoryG, $this->options->getNamespace('repositoryPlaceholder') );

        $pgRecordConverter = new PgRecordConverter( $entityG, $this->options->getNamespace('pgRecordConverter') );

        $this->generate( $entityG, true );
        $this->generate( $entityPlaceholderG, $this->options->regenerateEntityPlaceholders );
        $this->generate( $repositoryG, true );
        $this->generate( $repositoryPlaceholderG, $this->options->regenerateRepositoryPlaceholders );

        $this->generate( $pgRecordConverter, true );

        // register freshly generated entity with Repository
        $namespaces = $this->options->namespaces;
        if( $this->entityManager ) {
            $this->entityManager->register(
                $entityPlaceholderG->class->class,
                $repositoryPlaceholderG->class->class
            );
        }

        $this->callbacks['log']('');

        return [ $name, $pgRecordConverter ];

    }

    private function buildEntityRegistration( array $entities, array $pgRecordConverters )
    {

        $registrations = [];
        foreach( $entities as $name ) {
            $registrations[] = [
                $this->options->getNamespace('entityPlaceholder').'\\'.$name,
                $this->options->getNamespace('repositoryPlaceholder').'\\'.$name,
            ];
        }

        $class = $this->options->getNamespace('register').'\\EntityManager';

        $emEntityRegistration = new EntityManagerBuilder(
            $class,
            $registrations,
            $pgRecordConverters
        );

        file_put_contents(
            "{$this->options->getPath('register')}/EntityManager.php",
            $emEntityRegistration->class->render()
        );

        return $class;

    }

    /**
     * Get the entity type. Defaults to 'base'. Set by '@normality-entity: ignore';
     * The code that handles the output of this function can be found in 'Normality::buildRelation';
     * @return string
     */
    private function getEntityGenerator( PgClass $pgclass, array $options, $entityFileStore )
    {

        // build entity
        $tags = $this->getNormalityTags();

        $generators = array(
            'Entity' => '\Bond\Normality\Generate\Entity',
            'Link' => '\Bond\Normality\Generate\Entity\Link',
            'Child' => '\Bond\Normality\Generate\Entity\Child',
        );

        if( isset( $tags['entity'] ) ) {
            $entity = $tags['entity'];
        } elseif ( $parent = $this->get('parent') ) {
            $entity = 'Child';
        } else {
            $entity = 'Entity';
        }

        foreach( $generators as $key => $generator ) {

            if( in_array( strtolower( $entity ), array( strtolower( $key ), strtolower( $generator ) ) ) ) {

                $ref = new \ReflectionClass( $generator );
                return $ref->newInstance( $this, $db, $options, $entityFileStore );

            }

        }

        throw new \RuntimeException( "No generator found for `{entity}` and relation `{$this->get('name')}`" );

    }

    /**
     * Convert characters that file systems don't handle well into a natural replacement. eg, ' ' => '_'
     * @param string $filename Input filename
     * @return string Cleaned filename
     */
    private static function fileSystemFriendly( $filename )
    {

        // some characters just don't go well in file systems. Fix this.
        $filename = str_replace( ' ' , '_', $filename );
        $filename = str_replace( ':' , '-', $filename );

        return $filename;

    }

}