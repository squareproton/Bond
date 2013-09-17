<?php

/*
 * (c) SquareProton <squareproton@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

include(__DIR__ . "/../../../bootstrap.php");

use Bond\Database\Exception\DatabaseAlreadyExistsException;
use Bond\Normality\DatabaseBuilder;
use Bond\Normality\Exception\AssetChangedException;
use Bond\Pg;
use Bond\Pg\Resource;

if(count($argv) != 2) {
    print "takes argument of asset directory/file\n";
    die(1);
}

$container = new \Symfony\Component\DependencyInjection\ContainerBuilder();
$configurator = new \Bond\Di\Configurator($container);
$configurator->load(__DIR__ . "/Di/ConnectionFactoryConfigurable.yml");
$container->compile();

$settingsDb = $container->get('connectionSettingsRW');

$databaseBuilder = new DatabaseBuilder();
try {
    $settingsDb = $databaseBuilder->create( $settingsDb, '-' );
} catch ( DatabaseAlreadyExistsException $e ) {
    $databaseBuilder->emptyDb( $settingsDb );
}

// make a connection to the new database
$db = new Pg( new Resource( $settingsDb ) );

try {
    $assets = $databaseBuilder->getSqlAssetsFromDirs(
        $db,
        [
            $argv[1],
//            "/home/captain/CaptainCourier/captain-courier/res/database/schema",
            __DIR__ . "/../../../../database/assets"
        ],
        $assetsResolved
    );
    $databaseBuilder->resolveByDirs(
        [
            $argv[1]
        ],
        $assets,
        $assetsResolved
    );
    // cleanup
} catch ( AssetChangedException $e ) {
    // work around a phpunit bug with serialization
    // don't get me wrong - this is totally stupid and is a duplication of work
    // https://github.com/sebastianbergmann/phpunit/issues/451
    $newException = new \Exception( $e->getMessage() );
    $db->resource->terminate();
    throw $newException;
}

$db->resource->terminate();