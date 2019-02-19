<?php

// load the appropriate kernel
require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$kernel->run();
