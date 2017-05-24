<?php

// load the appropriate kernel

$kernel = require_once __DIR__ . '/../library/Bootstrap.php';

$mapper = Schoolbox\Resource\Folder\Mapper::getInstance();
$collection = $mapper->findByParentId(114);

$kernel->run();
