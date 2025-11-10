#!/usr/bin/env php
<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

require_once __DIR__ . '/../../conf/core/require.php';

$app = new \Schoolbox\Firebase\Credentials\PopulatorTool();

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, []))->runApp();
