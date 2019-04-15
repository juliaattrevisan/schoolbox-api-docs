#!/usr/bin/php
<?php
/**
 * Run the repair implementation assessments script.
 *
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

passthru(__DIR__ .  '/../../../tools/rubric/mergeDuplicateIndicators.php -vvv --perform --mailto=support@schoolbox.com.au', $returnVar);
exit($returnVar);
