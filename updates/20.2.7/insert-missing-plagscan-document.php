#!/usr/bin/php
<?php

/**
 * Run insert-missing-plagscan-document script.
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

if (!file_exists('/etc/schoolbox/instances.ini')) {
    echo "This script runs only on a production server.\n";
    exit();
}
pcntl_exec(__DIR__ . '/../../tools/plagscan/insert-missing-plagscan-document.sh');
