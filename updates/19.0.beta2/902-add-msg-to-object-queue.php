#!/usr/bin/php
<?php
/**
 * Run the script to add messages in redis to object message set.
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);
passthru(__DIR__ .  '/../../tools/notif/add-msg-to-object-queue.php --perform', $returnVar);
exit($returnVar);
