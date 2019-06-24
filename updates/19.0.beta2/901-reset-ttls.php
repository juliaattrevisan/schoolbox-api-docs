#!/usr/bin/php
<?php
/**
 * Run the reset ttl script.
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru(__DIR__ .  '/../../tools/mq/reset-ttls.php --perform', $returnVar);
exit($returnVar);
