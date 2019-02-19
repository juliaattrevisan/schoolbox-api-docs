<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$kernel = require_once __DIR__ . '/../../conf/core/require-web.php';

/** @var Schoolbox\Application\WebRequest\Kernel $kernel */
$externalDb = $kernel->get('databaseExternal');
$liss = new External_Liss_Server($externalDb);

$log = $liss->log();

try {
    $liss->start();
}
catch (Throwable $e) {
    $log->critical("Internal Error: {$e->getMessage()}");
}
