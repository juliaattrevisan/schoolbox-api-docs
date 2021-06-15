#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\MobileApp\CommandLineTool\RemoveDuplicates;

require_once __DIR__ . '/../../../conf/core/require.php';

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel(new RemoveDuplicates()))->runApp();
