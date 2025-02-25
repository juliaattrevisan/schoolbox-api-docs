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

// Start transaction differently now:
// add a name
// convert setTransactionContextForAuraRoute, etc. to spans
$trace->startTransaction(
    name: 'url:' . $path,
    operation: 'http.server',
    origin: 'auto.http',
    source: TransactionSource::url(),
    data: [
        'url'                 => $_SERVER['REQUEST_URI'] ?? null,
        'http.request.method' => $_SERVER['REQUEST_METHOD'] ?? null,
    ]
);
register_shutdown_function($trace->finishTransaction(...));

if ($path !== "/index.php" && preg_match('/.+\.php$/', $path) && file_exists(WEB_root . $path)) {
    // Script will load a Basic or Full Web kernel
    require WEB_root . $path;
} else {
    // Full Web kernel
    $kernel = new WebRequest\Kernel();
    $kernel->run();
}
