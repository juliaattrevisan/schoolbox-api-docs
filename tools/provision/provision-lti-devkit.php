#!/usr/bin/env php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Application\CliTool\Kernel;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Kernel(
    new class extends App {
        public function run(): void
        {
            echo 'b';
        }
    },
    [
        CliKernelAbstract::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
