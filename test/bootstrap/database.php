<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

// Set up class autoloader via conf/core/require:
// this loads database config from instances.ini.
require_once __DIR__ . '/../../conf/core/require.php';

// Set up DI container in mappers: this is unfortunately necessary in some tests
Schoolbox\Mapper::di((new Aura\Di\ContainerBuilder())->newInstance());
