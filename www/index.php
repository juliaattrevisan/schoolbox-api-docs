<?php

// load the appropriate kernel
/** @var Schoolbox\Application\WebRequest\Kernel $kernel */
$kernel = require_once __DIR__ . '/../conf/core/require-web.php';
$kernel->run();
