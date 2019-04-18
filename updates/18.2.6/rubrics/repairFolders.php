#!/usr/bin/php
<?php
/**
 * Run the repair folders script.
 *
 * This consists of:
 * * merge duplicate capabilities
 * * merge duplicate indicators
 * * delete duplicate indicators
 * * delete duplicate capabilities
 * in that order.
 *
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

passthru(__DIR__ .  '/../../../tools/rubric/repairFolders.php -vvv --perform --mailto=support@schoolbox.com.au', $returnVar);
exit($returnVar);
