#!/usr/bin/php
<?php
/**
 * Run the deleting old trigger script.
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru(__DIR__ .  '/../../tools/mq/remove-old-triggers.php --perform', $returnVar);
exit($returnVar);
