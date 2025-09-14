<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

require_once __DIR__ . '/../../conf/core/require.php';

// sorry.
Schoolbox\Mapper::di((new Aura\Di\ContainerBuilder())->newInstance());
