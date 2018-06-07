<?php

// load the appropriate kernel
/** @var Schoolbox\Application\WebRequest\Kernel $kernel */
$kernel = require_once __DIR__ . '/../library/Bootstrap.php';
$kernel->run();
