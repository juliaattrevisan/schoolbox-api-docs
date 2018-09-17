<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

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
