<?php

/**
 * This is now redundant - HTTP 401 Gone
 *
 *    |\      _,,,---,,_
 *   /,`.-'`'    -.  ;-;;,_
 *  |,4-  ) )-,_..;\ (  `'-'
 * '---''(_/--'  `-'\_)
 *
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
SitePage::localRedir('/', null, 410);
