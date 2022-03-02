<?php

// Front controller for all web requests
use Schoolbox\Application\WebRequest;
use Schoolbox\Application\WebRequest\RequestUriParser;

require_once __DIR__ . '/../conf/core/require.php';

$path = RequestUriParser::parseUrlPath($_SERVER);
if ($path !== "/index.php" && preg_match('/.+\.php$/', $path) && file_exists(WEB_root . $path)) {
    // Script will load a Basic or Full Web kernel
    require WEB_root . $path;
} else {
    // Full Web kernel
    $kernel = new WebRequest\Kernel();
    $kernel->run();
}

# TODO remove debug . Trigger Build
