#!/usr/bin/php
<?php

/**
 * This is how to delete EVERYTHING in RabbitMQ. Only do this if you're entirely confident that you want to do so,
 * because there is no undoing it. </warning>
 *
 * This is a modified version of destroy-rabbit-data.php that can be used when running in CI.
 * Due to how the CI environment is set up, rabbitmq needs users/vhosts re-added after a reset.
 *
 * This will likely become redundant once Schoolbox is containerised (IST-439)
 */

if (posix_getuid() !== 0) {
    // this method can only succeed if the user is root - don't even try otherwise
    echo "Make me a sandwich.\n";
    exit(1);
}


$appRoot = realpath(__DIR__ . '/../..');
$logDir = "/var/log/schoolbox/smoketest";

echo "[1/6] Stopping RabbitMQ\n";
`rabbitmqctl stop_app`;
echo "[2/6] Destroying all data in RabbitMQ\n";
`rabbitmqctl reset`;
echo "[3/6] Starting RabbitMQ\n";
`rabbitmqctl start_app`;
echo "[4/6] Recreating users and vhosts for CI\n";
`rabbitmqctl add_user schoolbox schoolbox`;
`rabbitmqctl add_vhost schoolbox`;
`rabbitmqctl set_permissions -p schoolbox schoolbox ".*" ".*" ".*"`;
echo "[5/6] Restarting RabbitMQ\n";
`service rabbitmq-server restart`;
echo "[6/6] Removing Redis data";
`redis-cli keys "msg:*" | xargs redis-cli del`;
echo "Finished.\n";
exit;
