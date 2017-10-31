<?php

// load the appropriate kernel
$kernel = require_once __DIR__ . '/../library/Bootstrap.php';
$mapper = Component_InstanceMapper::getInstance();
$model = $mapper->findById(3639);
$kernel->run();
