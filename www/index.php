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

if ($path !== "/index.php" && preg_match('/.+\.php$/', $path) && file_exists(WEB_root . $path)) {
    // Script will load a Basic or Full Web kernel
    require WEB_root . $path;
} else {
    // Full Web kernel
    // The transaction's actual name will be set later.
    $trace = Tracer::getDefaultTrace();
    $trace->setServerVars($_SERVER);
    $transactionContext = $trace->startTransaction(startTimestamp: microtime(true));
    $kernel = new WebRequest\Kernel();
    $kernel->run();
    $trace->finishTransaction($kernel->get('sentry'), endTimestamp: microtime(true));
}
