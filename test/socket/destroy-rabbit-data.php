#!/usr/bin/php
<?php
/**
 * This is how to delete EVERYTHING in RabbitMQ. Only do this if you're entirely confident that you want to do so,
 * because there is no undoing it. </warning>
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(posix_getuid() !== 0) {
	// this method can only succeed if the user is root - don't even try otherwise
	echo "Make me a sandwich.\n";
	exit(1);
}


$appRoot = realpath(__DIR__ . '/../..');

echo "[1/5] Stopping RabbitMQ\n";
`rabbitmqctl stop_app`;
echo "[2/5] Destroying all data in RabbitMQ\n";
`rabbitmqctl reset`;
echo "[3/5] Restarting RabbitMQ\n";
`service rabbitmq-server restart`;
echo "[4/5] Rebinding queues\n";
`$appRoot/tools/mq/binder.php`;
echo "[5/5] Removing Redis data";
`redis-cli keys "msg:*" | xargs redis-cli del`;
echo "Finished.\n";
exit;