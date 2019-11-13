<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$externalDb = $kernel->get('databaseExternal');
$liss = new External_Liss_Server($kernel->get('config'), $externalDb);

$log = $liss->log();

try {
    $liss->start();
}
catch (Throwable $e) {
    $log->critical("Internal Error: {$e->getMessage()}");
}
