<?php

// load the appropriate kernel
require_once __DIR__ . '/../conf/core/require.php';
spl_autoload_call('Schoolbox\\Application\\WebRequest');

$kernel = new \Schoolbox\Application\WebRequest\Kernel();
$kernel->run();
