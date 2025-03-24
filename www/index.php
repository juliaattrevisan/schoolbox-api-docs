<?php

// Front controller for all web requests
use Alaress\Sentry\Tracing\Tracer;
use Schoolbox\Application\WebRequest;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Sentry\Tracing\TransactionSource;
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
    // First set a basic context for this request:
    // this may be overwritten later if using the basic kernel.
    $trace->setTransactionContextForSitePage(WEB_root . $path);

    // Script will load a Basic or Full Web kernel
    require WEB_root . $path;
} else {
    // Full Web kernel
    $kernel = new WebRequest\Kernel();
    $kernel->run();
}
