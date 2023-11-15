#!/usr/bin/php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Fetches Learnosity credentials from IR
 * Populates instance's database
 */
$app = new \Schoolbox\Learning\Learnosity\Credentials\PopulatorTool();

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
