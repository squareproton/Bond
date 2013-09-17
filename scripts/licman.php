#! /usr/bin/env php
<?php

/**
 * Created by PhpStorm.
 * User: joseph
 * Date: 30/08/2013
 * Time: 12:32
 */

// Licence Manager (LicMan)

////////// OPTIONS //////////
// -r     dry run, outputs all files that will change, does not edit files
// -i     inline, writes to the files
// -d     delete license headers
// -v     verbose
//        with no args, will output file and its new contents, no changes to filesystem
////////// OPTIONS //////////

////////// CONFIGURATION /////////
// file containing the licence header relative to the script's location
$licenseFile = __DIR__ . "/licman/licenceheader.txt";
////////// CONFIGURATION /////////


$structurePrefixes = ["class", "trait", "interface", "abstract class"];


class UnexpectedTokenException extends Exception
{
    public function __construct($token, $line)
    {
        parent::__construct(
            "Unexpected token '$token' on line $line"
        );
    }
}

function hasLicense(array $lines)
{
    if (!lineIndexesOfDocBlock($lines)) {
        return false;
    }
    List($start, $finish) = lineIndexesOfDocBlock($lines);
    $licenseContent = implode(" ", array_slice($lines, $start, $finish-$start+1));
    return strpos($licenseContent, "LICENSE") !== false;
}

/**
 * returns tuple of first and last line index of docblock
 * if their is no script docblock before namespace/class etc then return null
 * @param $contents
 */
function lineIndexesOfDocBlock(array $lines)
{
    $start = -1;

    for ($i = 0; $i < count($lines); $i++) {

        $line = $lines[$i];

        // if line starts with '/**' and start is -1 then set start to index
        // if line starts with '/**' and start not -1 then throw UnexpectedTokenException
        if (startsWith($line, '/**') or startsWith($line, '/*')) {
            if (-1 === $start) {
                $start = $i;
            } else {
                throw new UnexpectedTokenException("/**", $i);
            }
        }
        // if line starts with ' */' and start is -1 then throw UnexpectedTokenException
        // if line starts with ' */' and start not -1 then return [start, i]
        else if (startsWith($line, ' */')) {
            if(-1 === $start) {
                throw new UnexpectedTokenException(' */', $i);
            } else {
                return [$start, $i];
            }
        }
        // if line starts with ' *' and start is -1 then continue
        // if line starts with ' *' and start not -1 then continue
        else if (startsWith($line, ' *')) {
            if (-1 === $start) {
                continue;
            } else {
                continue;
            }
        }
        // if line starts with class/abstract class/trait/interface then return null
        else if (
            startsWith($line, 'class') or
            startsWith($line, 'trait') or
            startsWith($line, "interface") or
            startsWith($line, 'abstract class')
        ) {
            return null;
        }
        // if line starts with anything else then continue
        else {
            continue;
        }
    }
    return null;
}

function removeLicense(array $lines)
{
    assert(lineIndexesOfDocBlock($lines) !== null);
    List($start, $finish) = lineIndexesOfDocBlock($lines);
    for ($i = $start; $i <= $finish; $i++) {
        unset($lines[$i]);
    }
    return array_values($lines);
}

function addLicense(array $contentLines)
{
    global $licenseFile;
    $licenseLines = explode("\n", file_get_contents($licenseFile));

    $contentLines = arrayInject($contentLines, [""], 1);
    assert($contentLines[0] === "<?php", implode("\n", $contentLines));
    assert($contentLines[1] === "");
    assert($contentLines[2] === "");

    return arrayInject($contentLines, $licenseLines, 2);

}

function cleanUpFileHeader(array $lines)
{
    $indexOfLastEmptyLine = -1;
    for ($i = 1; $i < count($lines); $i++) {
        if ("" === trim($lines[$i])) {
            $indexOfLastEmptyLine = $i;
        } else {
            break;
        }
    }

    if ($indexOfLastEmptyLine === -1) {
        $lines = arrayInject($lines, ["", ""], 1);
    } else if ($indexOfLastEmptyLine === 1) {
        $lines = arrayInject($lines, [""], 1);
    } else if ($indexOfLastEmptyLine > 2) {
        $numLinesDelete = $indexOfLastEmptyLine - 2;
        for ($i = 0; $i < $numLinesDelete; $i++) {
            print "deleting line ${lines[1]}\n";
            unset($lines[1]);
            $lines = array_values($lines);
        }
    }

    return $lines;
}


function getFileset($src)
{
    $whiteList = [".php"];
    if (is_file($src)) {
        return [$src];
    } else {
        $files = [];
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath($src)));
        foreach($objects as $name => $object) {
            $matches = false;
            foreach ($whiteList as $validExtension) {
                if (endsWith($name, $validExtension)) {
                    $matches = true;
                    break;
                }
            }
            if ($matches) {
                $files[] = $name;
            }

        }
        return $files;
    }
}

function hasNamespace($lines)
{
    return checkForPrefix($lines, ["namespace"], true);
}

function isScript($lines)
{
    global $structurePrefixes;
    $prefixes = $structurePrefixes;
    return !checkForPrefix($lines, $prefixes, true);
}

function checkForPrefix($lines, $prefixes, $check=false)
{
    $prefixLines = [];
    for($i = 0; $i < count($lines); $i++) {
        foreach ($prefixes as $prefix) {
            if (startsWith($lines[$i], $prefix)) {
                $prefixLines[$i] = $prefix;
            }
        }
    }
    return $check ? (count($prefixLines) > 0) : $prefixLines;
}

function updateLicense($lines)
{
    return addLicense(
        cleanUpFileHeader(hasLicense($lines) ? removeLicense($lines) : $lines)
    );
}

function willContentChange($lines)
{
    return implode("\n", $lines) !== implode("\n", updateLicense($lines));
}

function main($args)
{
    $dryrun = in_array("-r", $args);
    $inline = in_array("-i", $args);
    $delete = in_array("-d", $args);
    $verbose =  in_array("-v", $args);
    $args = array_values(array_filter($args, function($arg){return !startsWith($arg, "-");}));
    $src = $args[0];
    if (!file_exists($src)) {
       throw new Exception("can't find file/folder '$src'");
    }

    if ($dryrun) {
        if($verbose) print "DRYRUN MODE\n";
        $changeFiles = [];
        foreach (getFileset($src) as $file) {
            if($verbose) print "CHECKING FILE '$file'\n";
            $file = realpath($file);
            $lines = explode("\n", file_get_contents($file));
            if(willContentChange($lines)) {
                $changeFiles[] = $file;
            }
        }
        print "FILES THAT WILL CHANGE\n";
        print implode("\n - ", $changeFiles);
    } else if ($delete) {
        if($verbose) print "LICENSE DELETE MODE\n";

        foreach (getFileset($src) as $file) {
            if($verbose) print "DELETING ANY LICENSE HEADERS FROM FILE '$file'\n";
            $lines = explode("\n", file_get_contents(realpath($file)));
            $lines = cleanUpFileHeader(removeLicense($lines));
            file_put_contents($file, implode("\n", $lines));
        }
    } else {

        $fileContents = [];
        foreach (getFileset($src) as $file) {
            if($verbose) print "UPDATING FILE '$file'\n";
            $file = realpath($file);
            $contents = file_get_contents($file);
            $lines = explode("\n", $contents);
            $fileContents[$file] = implode("\n", updateLicense($lines));
        }

        if ($inline) {
            if($verbose) print "INLINE MODE - WRITING FILES...\n";
            foreach ($fileContents as $fileName => $contents) {
                if($verbose) print "WRITING FILE '$fileName'\n";
                file_put_contents($fileName, $contents);
            }
            print "WROTE " . count($fileContents) . " FILES\n";
        } else {
            foreach ($fileContents as $fileName => $contents) {
                print ">>>FILE $fileName\n$contents\n<<<FILE\n";
            }
        }

    }
}



///////////// helpers ////////////////


function arrayInject(array $data, array $inject, $index)
{
    if (0 === $index) {
        return array_merge($inject, $data);
    } elseif ($index > count($data)) {
        throw new \InvalidArgumentException("index cannot be greater than length of data array");
    } elseif (count($data) === $index) {
        return array_merge($data, $inject);
    } else {
        $start = array_slice($data, 0, $index);
        $end = array_splice($data, $index);
        return array_merge(array_merge($start, $inject), $end);
    }
}


function startsWith($haystack, $needle)
{
    return $needle === "" || strpos($haystack, $needle) === 0;
}
function endsWith($haystack, $needle)
{
    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
}


main(array_slice($argv, 1));