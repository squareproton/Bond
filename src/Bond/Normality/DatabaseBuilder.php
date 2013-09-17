<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Bond\Normality;

use Bond\DependencyResolver;
use Bond\DependencyResolver\ResolverList;
use Bond\DependencyResolver\Sql as DependencyResolverSql;

use Bond\MagicGetter;

use Bond\Normality\Exception\AssetChangedException;

use Bond\Pg;
use Bond\Database\Exception\DatabaseAlreadyExistsException;
use Bond\Pg\Exception\States\State55006 as ObjectInUse;
use Bond\Pg\Resource;
use Bond\Pg\Result;
use Bond\Pg\ConnectionSettings;

use Bond\Sql\Query;
use Bond\Sql\Raw;

/**
 * @desc Resolve a directory of .sql files into a resolver block
 */
class DatabaseBuilder
{

    /**
     * Empty a database entirely from some passed database settings
     *
     * @param Bond\Pg\Settings
     */
    public function emptyDb( ConnectionSettings $settings )
    {
        $db = new Pg( new Resource( $settings ) );
        $db->query( new Query( "SELECT build.empty_db()" ) );
        $db->resource->terminate();

        return $this;
    }

    /**
     * Truncate a database and don't destroy relations
     *
     * @param Bond\Pg\Settings
     */
    public function truncateDb( ConnectionSettings $settings )
    {
        $db = new Pg( new Resource( $settings ) );
        $db->query( new Query( "SELECT build.truncate_db()" ) );
        $db->resource->terminate();

        return $this;
    }

    /**
     * Generate a empty database
     *
     * @param string database Name
     * @param string database version
     * @param \Callable Callback called when database has been created
     */
    public function create( ConnectionSettings $settingsDb, $version = '-', callable $onCreateCallback = null )
    {

        $settingsPostgres = clone $settingsDb;
        $settingsPostgres->dbname = 'postgres';

        $db = new Pg( new Resource( $settingsPostgres ) );

        $result = $db->query(
            new Query(
                "SELECT * FROM pg_catalog.pg_database WHERE datname = %dbname:%",
                array( 'dbname' => $settingsDb->dbname )
            )
        );

        // does database exist?
        // does database exist?
        if( $result->fetch( Result::FETCH_SINGLE ) ) {

            $db->resource->terminate();

            $hasBuildSchema = new Query( <<<SQL
                SELECT
                    count(*) = 1
                FROM
                    pg_namespace
                WHERE
                    nspowner = ( SELECT datdba FROM pg_database WHERE datname = %dbname:% ) AND
                    nspname = 'build'
SQL
            );
            $hasBuildSchema->dbname = $settingsDb->dbname;

            // check build schema exists
            $db = new Pg( new Resource( $settingsDb ) );
            if( !$db->query($hasBuildSchema)->fetch(Result::TYPE_DETECT|Result::FETCH_SINGLE) ) {
                $db->query( $this->getSetupSql() );
            }
            $db->resource->terminate();

            throw new DatabaseAlreadyExistsException( $settingsDb->dbname );

        } else {

            $create = new Query( <<<SQL
                CREATE DATABASE
                    %dbname:identifier%
                TEMPLATE template0
                ENCODING 'UTF8' LC_COLLATE 'en_GB.UTF-8' LC_CTYPE 'en_GB.UTF-8'
                CONNECTION LIMIT -1;
SQL
            );

            $create->dbname = $settingsDb->dbname;
            $db->query( $create );

        }

        $alter = new Query( <<<SQL
            ALTER DATABASE %dbname:identifier% SET bond.build_date = %build_date:%;
            ALTER DATABASE %dbname:identifier% SET bond.version = %version:%;
SQL
        );
        $alter->dbname = $settingsDb->dbname;
        $alter->build_date = date( 'YmdHis' );
        $alter->version = $version;
        $db->query( $alter );

        $db->resource->terminate();
        unset( $db );

        // make connection to freshly created database and run our setupsql on it
        $db = new Pg( new Resource( $settingsDb ) );
        $db->query( $this->getSetupSql() );

        if( $onCreateCallback ) {
            call_user_func( $onCreateCallback, $db );
        }

        $db->resource->terminate();
        unset( $db );

        // return connection settings
        return $settingsDb;

    }

    /**
     * @desc Take a array of asset directories and return a array
     * @return \SplFileInfo[];
     */
    public function getSqlAssetsFromDirs1( array $dirs )
    {

        $assetChecker = function( \SplFileInfo $file ) {
            return $file->isFile() and $file->getExtension() === 'sql';
        };

        $assetsViaDirectories = new \AppendIterator();
        $assetsViaFiles = [];

        foreach( $dirs as $dir ) {

            // is a directory
            if( is_dir($dir) ) {
                $assetsViaDirectories->append(
                    new \CallbackFilterIterator(
                        new \RecursiveIteratorIterator(
                            new \RecursiveDirectoryIterator( $dir )
                        ),
                        $assetChecker
                    )
                );
            } else {
                // is a file?
                // is it good?
                $assetViaFile = new \SplFileInfo($dir);
                if( $assetChecker($assetViaFile) ) {
                    $assetsViaFiles[] = $assetViaFile;
                }
            }
        }

        // remove duplicate directory entries
        $assets = array_merge(
            iterator_to_array( $assetsViaDirectories ),
            $assetsViaFiles
        );
        $assets = array_unique( $assets );

        return $assets;

    }

    /**
     * @desc Build a (sql) resolver list
     * @param Bond\Pg
     * @return Bond\DependencyResolver\ResolverList
     */
    public function getSqlAssetsFromDirs( Pg $db, $dirs, ResolverList &$resolved = null )
    {

        if( !$resolved ) {
            $resolved = new ResolverList();
        }

        $sqls = new ResolverList();

        $log = function(){
            echo $this->getId() ."\n";
        };

        $dbAssets = $this->getLoadedAssets($db);

        foreach( $this->getSqlAssetsFromDirs1( $dirs ) as $file ) {

            $name = substr( $file->getBasename(), 0, -4 );
            $location = realpath( $file->getPathname() );
            $sql = file_get_contents( $location );
            $sqlMd5 = md5( $sql );

            $options = array(
                // insert into the build.assets table
                'POST_RESOLVE' => function() use ( $db, $name, $location, $sql ) {
                    $db->query(
                        new Query(
                            "INSERT INTO build.assets( name, location, sql ) VALUES( %name:%, %location:%, %sql:% )",
                            array(
                                'name' => $name,
                                'location' => $location,
                                'sql' => $sql,
                            )
                        )
                    );
                },
            );

            // make asset and add to asset list
            $asset = new DependencyResolverSql( $name, $db, $sql, $options );
            $sqls[] = $asset;

            // has this already been resolved according to build.assets?
            if( isset( $dbAssets[$name] ) ) {
                $resolved[] = $asset;
                // check the asset in the database has the same structure as the new one in the filesystem
                if( $dbAssets[$name] !== $sqlMd5 ) {
                    throw new AssetChangedException( $file, $db );
                }
            }

        }

        // build interrelated sql asset dependencies
        foreach( clone $sqls as $sql ) {
            $sql->setSqlDepends( $sqls );
        }

        return $sqls;

    }

    /**
     * Resolve a entire assets directory
     * @return ResolverList
     */
    public function resolveAll( ResolverList $assets, ResolverList $resolved )
    {

        // apply all obs to the database
        $resolveAll = new DependencyResolver(
            "resolveAll-" . spl_object_hash($assets),
            function() use ( $assets ) {
                // echo "resolved all assets - \n" . (string) $assets;
            }
        );

        // add all our resources as the database build setup
        foreach( $assets as $dependency ) {
            $resolveAll->addDependency( $dependency );
        }

        // actually execute the tables
        $resolveAll->resolve( $resolved, new ResolverList(), true );

        return $resolved;

    }

    /**
     * Resolve a entire assets directory
     * @return ResolverList
     */
    public function resolveByDirs( array $dirs, ResolverList $assets, ResolverList $resolved )
    {

        // apply all obs to the database
        $resolveByDirs = new DependencyResolver(
            "resolveByDirs-" . json_encode($dirs),
            function() use ( $dirs, $assets ) {
                return true;
                printf(
                    "Resolved assets in directories\n%s\n",
                    json_encode( $dirs,  JSON_PRETTY_PRINT )
                );
            }
        );

        // add all our resources as the database build setup
        foreach( $this->getSqlAssetsFromDirs1($dirs) as $file ) {
//            print_r( $file );
            $name = substr( $file->getBasename(), 0, -4 );
            if( $assets->containsId($name) ) {
                $resolveByDirs->addDependency( $assets->getById($name) );
            }
        }

        $resolveByDirs->resolve( $resolved, new ResolverList(), true );

        return $resolved;

    }

    public function fixSequences( Pg $db )
    {

        $query = new Query( <<<SQL
            SELECT
                a.attname as col,
                c.relname as tbl,
                n.nspname as schema,
                quote_ident(n.nspname) || '.' || split_part( d.adsrc, E'\'', 2 ) AS sequence
            FROM
                pg_attrdef as d
            INNER JOIN
                pg_attribute as a ON d.adrelid = a.attrelid AND d.adnum = a.attnum
            INNER JOIN
                pg_class as c ON a.attrelid = c.oid
            INNER JOIN
                pg_namespace n ON n.oid = c.relnamespace
            WHERE
                d.adsrc LIKE 'nextval%'
SQL
        );

        $result = $db->query( $query );

        $fixed = [];
        foreach( $result->fetch() as $row ) {

            // get sequence value -- this might throw a error if the sequence hasn't been used yet
            try {
                $result = $db->query(
                    // can't use curval here because that only works on a per session basis. fuck
                    // possible workaround (if you were to give that much of a fuck) would be to call postgres setval( $name, $value, true )
                    // see http://www.postgresql.org/docs/current/static/functions-sequence.html

                    // UPDATE. Yippie. It seems as though postgres treats sequences as tables with exactly 1 row.
                    // This means it is possible to non destructively query a sequence without the 'you-must-have-used-this-before' currval issues.
                    // Think - SELECT * FROM "sequenceName"
                    // TODO get this done.
                    new Query( "SELECT nextval('{$row['sequence']}'::regclass);" )
                );
                $sequenceNo = $result->fetch( Result::FETCH_SINGLE );
            } catch ( \State55000 $e ) {
                $sequenceNo = 0;
            }

            // get max value in sequence column
            $result = $db->query(
                new Raw(
                    sprintf(
                        "SELECT COALESCE( MAX(%s), 0 ) FROM %s.%s",
                        $db->quoteIdent( $row['col'] ),
                        $db->quoteIdent( $row['schema'] ),
                        $db->quoteIdent( $row['tbl'] )
                    )
                )
            );
            $maxValue = $result->fetch( Result::FETCH_SINGLE );

            // d_pr( [$row['sequence'], $maxValue, $sequenceNo ] );

            // does sequence need operating on?
            if( $sequenceNo < $maxValue ) {

                $maxValue++;
                $fixed[$row['sequence']] = $maxValue;
                $db->query(
                    new Raw(
                        sprintf(
                            "SELECT setval('%s'::regclass, %d )",
                            $row['sequence'],
                            $maxValue
                        )
                    )
                );

            }

        }

        return $fixed;
    }

    /**
     * Get loaded assets
     * @param Bond\Pg
     * @param array
     */
    private function getLoadedAssets( Pg $db )
    {
        $result = $db->query(
            new Query("SELECT * FROM build.assets")
        );

        $output = [];
        foreach( $result as $row ) {
            $output[$row['name']] = md5( $row['sql'] );

        }
        return $output;
    }

    /**
     * Get asset dir mtimes
     * @param Bond\Pg
     * @param array
     */
    private function getAssetDirMtimes( Pg $db )
    {
        // todo watch the asset dirs for changes

    }

    /**
     * Each normality database has a schema called build info which records the what assets have been inserted into the database
     * @return Bond\Sql\Query;
     */
    private function getSetupSql()
    {

        return new Raw( <<<'SQL'

            DROP SCHEMA IF EXISTS build CASCADE;
            CREATE SCHEMA build;
            SET search_path TO build;

            CREATE TABLE "assets" (
                "name" text NOT NULL,
                "location" text NOT NULL,
                "sql" text NOT NULL,
                "when" timestamp NOT NULL DEFAULT NOW(),
                CONSTRAINT "pk_assets" PRIMARY KEY ("name")
            );

            CREATE TABLE "log" (
                "log" text NOT NULL,
                "when" timestamp NOT NULL DEFAULT NOW()
            );

            -- insert a record into the build.log
            CREATE FUNCTION "log"( text ) RETURNS timestamp AS
            $$
                INSERT INTO build.log(log) values ($1) returning "when";
            $$ LANGUAGE SQL;

            -- truncate if exists safe table truncation
            CREATE FUNCTION "truncate_table"( text ) RETURNS bool AS
            $$
            BEGIN
                EXECUTE 'TRUNCATE ' || $1 || ' RESTART IDENTITY CASCADE;';
                RETURN TRUE;
            EXCEPTION
                WHEN SQLSTATE '42P01' THEN
                    RETURN false;
            END;
            $$ LANGUAGE plpgsql;

            -- truncate every table in a schema
            CREATE FUNCTION "truncate_schema"( text ) RETURNS text AS
            $$
            DECLARE
                schema text;
            BEGIN

                PERFORM
                    build.truncate_table( quote_ident(n.nspname) || '.' || quote_ident(c.relname) ),
                    build.log( 'truncating schema ' || quote_ident(n.nspname) )
                FROM
                    pg_class c
                INNER JOIN
                    pg_namespace n ON c.relnamespace = n.oid
                WHERE
                    c.relkind = 'r' AND
                    n.nspname = $1
                ;

                RETURN $1;

            END;
            $$ LANGUAGE plpgsql;

            -- Remove all schema from the database and truncate build.assets table
            CREATE FUNCTION "empty_db"() RETURNS void AS
            $$
            DECLARE
                schema text;
            BEGIN

                -- // drop all schemas that aren't in build
                FOR schema IN (
                    SELECT
                        nspname
                    FROM
                        pg_namespace n
                    INNER JOIN
                        pg_authid a ON n.nspowner = a.oid AND a.rolname = current_user
                    WHERE
                        nspname != 'build'
                ) LOOP
                    EXECUTE 'DROP SCHEMA ' || quote_ident( schema ) || ' CASCADE;';
                END LOOP;

                PERFORM build.truncate_table( 'build.assets' );
                PERFORM build.log('empty_db');

            END;
            $$ LANGUAGE plpgsql;

            -- Truncate database
            CREATE FUNCTION truncate_db() RETURNS text[] AS
            $$
                SELECT
                    array_agg( build.truncate_schema( nspname::text ) )
                FROM
                    pg_namespace n
                INNER JOIN
                    pg_authid a ON n.nspowner = a.oid AND a.rolname = current_user
                WHERE
                    nspname != 'build'
            $$ LANGUAGE SQL;

            -- Add a schema into the search_path
            CREATE FUNCTION "search_path_add_schema"( text, p int, alter_database bool ) RETURNS text AS
            $$
            DECLARE
                original_search_path TEXT;
                new_search_path TEXT;
            BEGIN

                -- EXECUTE 'SET search_path TO default';
                EXECUTE 'SHOW search_path;' INTO original_search_path;

                WITH existing AS (
                    SELECT
                        trim( '"' FROM trim(s) ) as schema,
                        row_number() over () as n
                    FROM
                        unnest( string_to_array( original_search_path, ',') ) as _ (s)
                        UNION ALL
                    SELECT
                        $1,
                        p
                ),
                dedup_quoted_ordered as (
                    SELECT
                        quote_ident(schema) AS schema,
                        MIN(n) n
                    FROM
                        existing
                    GROUP BY
                        schema
                    ORDER BY MIN(n)
                )
                SELECT string_agg(schema, ', ') INTO new_search_path FROM dedup_quoted_ordered;

                IF original_search_path <> new_search_path THEN

                    EXECUTE 'SET search_path TO ' || new_search_path;

                    IF alter_database THEN
                        EXECUTE 'ALTER DATABASE ' || current_database() || ' SET search_path FROM CURRENT';
                    END IF;

                END IF;

                RETURN new_search_path;

            END;
            $$ LANGUAGE plpgsql;

SQL
);

    }

}
