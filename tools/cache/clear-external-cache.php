#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Alaress\Cache\CommandLineTool\ClearExternal;

require_once __DIR__ . '/../../conf/core/require.php';

/** @noinspection PhpUnhandledExceptionInspection */
new Schoolbox\Application\CliTool\Kernel(new ClearExternal(), [])->runApp();
