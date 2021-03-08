#!/usr/bin/php
<?php
/**
 * Run insert-missing-plagscan-document script.
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

$result = pcntl_exec(__DIR__ . '/../../tools/plagscan/insert-missing-plagscan-document.sh');
echo $result;
