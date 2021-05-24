#!/usr/bin/php
<?php

/**
 * Run the deleting old trigger script.
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

$result = pcntl_exec(__DIR__ .  '/../../tools/mq/clean-up-redis.sh');
echo $result;
