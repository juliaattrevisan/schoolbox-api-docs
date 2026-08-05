<?php

// Front controller for all web requests
use Alaress\Sentry\Tracing\Tracer;
use Schoolbox\Application\WebRequest;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

require_once __DIR__ . '/../conf/core/require.php';

try {
    $path = RequestUriParser::parseUrlPath($_SERVER);
} catch (BadRequestException $e) {
    http_response_code(400);
    die($e->getMessage());
}

// Start tracing
// The transaction's actual name will be set later.
$trace = Tracer::getDefaultTrace();
$trace->setServerVars($_SERVER);

// Start transaction ASAP for profiling:
// later, setTransactionContextForAuraRoute, etc. will update the transaction.
$trace->startTransaction();
register_shutdown_function($trace->finishTransaction(...));

if ($path !== "/index.php" && preg_match('/.+\.php$/', $path) && file_exists(WEB_root . $path)) {
    // Verify that $path resolves inside WEB_root, and exit otherwise
    $webRootRealpath = realpath(WEB_root);
    $realpath        = realpath(WEB_root . $path);

    // Web root or path not found? Exit
    if ($webRootRealpath === false || $realpath === false) {
        http_response_code(404);
        exit;
    }

    // Verify that path is in the webroot, or a subfolder thereof
    $webRootPrefix = rtrim($webRootRealpath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($realpath, $webRootPrefix)) {
        http_response_code(404);
        exit;
    }

    // First set a basic context for this request:
    // this may be overwritten later if using the basic kernel.
    $trace->setTransactionContextForSitePage($realpath);

    // Script will load a Basic or Full Web kernel
    require $realpath;
} else {
    // Full Web kernel
    $kernel = new WebRequest\Kernel();
    $kernel->run();
}
