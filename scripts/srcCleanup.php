<?php

$iterator = new \CallbackFilterIterator(
    new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator( __DIR__ . '/../src/Bond/' )
    ),
    function( \SplFileInfo $file ) {
        return $file->isFile() and $file->getExtension() === 'php';
    }
);

foreach( $iterator as $file ) {
    echo fix( $file ) ? "{$file->getRealPath()}\n" : '';
}

function fix ( $file ) {

    $contents = file_get_contents( $file->getRealPath() );

    // rtrim every line
    $string = implode(
        "\n",
        array_map(
            function( $line ) {
                return rtrim( str_replace("\t", "    ",  $line ) );
            },
            explode( "\n", $contents )
        )
    );

    $regex = '/\\v{3,}/m';

    file_put_contents(
        $file->getRealPath(),
        preg_replace( $regex, "\n\n", $string )
    );

    return $contents !== $string;

}