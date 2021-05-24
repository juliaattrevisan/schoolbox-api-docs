#!/usr/bin/php
<?php

/**
 * Run a SVG sanitiser over all SVGs in the system
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru(__DIR__ .  '/../../tools/image/sanitize.php -p', $returnVar);
exit($returnVar);
