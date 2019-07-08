#!/usr/bin/php
<?php
/**
 * Usage: ./gc-storage.php [--hash] [-p] [-vvv]
 *
 * A tool that will perform a removal of file in storage by the provided hash
 *
 *  -p          perform: *without* this flag, GC will only do a dry run
 *  -v[v[v]]    verbosity: how much activity will be logged
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Lumberjack\Logger;
use Schoolbox\Application\Daemon;
use Schoolbox\Resource\Storage\RelationalDelete\RelationalDelete;

$errors  = [];
$opts    = getopt('pvn:', ['hash:']);
$performDelete = !isset($opts['p']);
$hash = $opts['hash'] ?? null;

require_once __DIR__ . '/../conf/core/require.php';
new Daemon\Kernel(
    'Storage Garbage Collection',
    [
        Daemon\Kernel::OPTION_ERRORS => E_ALL
    ]
);

$threshold = 0;
if(isset($opts['v'])) {
    $threshold = 1;
    if(is_array($opts['v'])) {
        $threshold = min(3, count($opts['v']));
    }
}

$limits = [
    0 => 500, // nil verbosity: exceptional behaviour only
    1 => 250, // min verbosity: significant events
    2 => 200, // mod verbosity: most things
    3 => 100  // max verbosity: EV-ER-Y-THING...
];

$log = new Logger(STDOUT, STDERR, $limits[$threshold]);

if (!$hash) {
    $log->error('[ERROR]: Hash cannot be empty...');
    die();
}

(new RelationalDelete($log))->remove($hash, $performDelete);

exit(0);
