#!/usr/bin/php
<?php

/**
 * This is how to delete EVERYTHING in RabbitMQ. Only do this if you're entirely confident that you want to do so,
 * because there is no undoing it. </warning>
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

if (posix_getuid() !== 0) {
    // this method can only succeed if the user is root - don't even try otherwise
    echo "Make me a sandwich.\n";
    exit(1);
}


$appRoot = realpath(__DIR__ . '/../..');

echo "[1/4] Stopping RabbitMQ\n";
`rabbitmqctl stop_app`;
echo "[2/4] Destroying all data in RabbitMQ\n";
`rabbitmqctl reset`;
echo "[3/4] Restarting RabbitMQ\n";
`service rabbitmq-server restart`;
echo "[4/4] Removing Redis data";
`redis-cli keys "msg:*" | xargs redis-cli del`;
echo "Finished.\n";
exit;
