<?php

/**
 * Hook for pre-commit
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// this is run per-file to be committed; $argv[1] is the filename

$filename = $argv[1];

// we're only interested in php files
if (substr($filename, strrpos($filename, '.')) != '.php') {
    exit(0);
}

// if the file has been deleted, we're not interested
if (!file_exists($filename)) {
    exit(0);
}

$path = explode('/', $filename);

// if a library or include file, attempt to run unit tests
if (in_array($path[0], array('library', 'include'))) {
    // 'library/Schoolbox/{Module}[/Namespacing]/Class.php' becomes
    // 'library/Schoolbox/{Module}/Test[/Namespacing]/ClassTest.php'

    // 'library|include/^Schoolbox[/Namespacing]/Class.php' becomes
    // 'library|include/^Schoolbox/Test[/Namespacing]/ClassTest.php'

    $testpath = $path;

    $pos = in_array($testpath[1], array('Schoolbox','Alaress')) ? min(count($testpath) - 1, 3) : 1;
    array_splice($testpath, $pos, 0, 'Test');

    $testpath = implode('/', str_replace('.php', 'Test.php', $path));

    // if unit test is found, run it
    if (file_exists($testpath)) {
        // get the autoloading for unit tests
        require_once __DIR__ . '/../../test/bootstrap/unit.php';

        $phpunit = __DIR__ . '/../../vendor/phpunit/phpunit/phpunit';

        // run unit test
        exec("$phpunit $testpath", $output, $return);

        // baulk if the tests failed, be silent otherwise
        if ($return != 0) {
            echo "$filename: Failed unit tests\n";
            echo implode("\n", $output) . "\n";
            exit($return);
        }
    }
}

// run coding standards filter; this will modify the non-indexed version of this file if it doesn't conform (doesn't touch index)
exec("git show :$filename | php-cs-fixer fix - | cut -b7-", $output, $return);

if (count($output) && $output[0] != 'stdin.php') {
    echo "$filename: Non-conformance to PSR-2\n";
    exec("php-cs-fixer fix $filename", $output, $return);
    exit(1);
}

exit(0);
