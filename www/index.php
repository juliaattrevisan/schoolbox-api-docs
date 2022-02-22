<?php

// Front controller for all web requests
use Schoolbox\Application\WebRequest;

require_once __DIR__ . '/../conf/core/require.php';

$page = $_GET['page'] ?? '';
$page = preg_replace("/\?.+$/", "", $page); // Strip any trailing GET params
if (preg_match('/.+\.php$/', $page) && file_exists(WEB_root . $page)) {
    // Script will load a Basic or Full Web kernel
    require WEB_root . $page;
} else {
    // Full Web kernel
    $kernel = new WebRequest\Kernel();
    $kernel->run();
}
