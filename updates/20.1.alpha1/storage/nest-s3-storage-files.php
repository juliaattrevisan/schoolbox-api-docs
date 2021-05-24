#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

// Copy storage/transform files to be below the 'storage/' prefix
passthru(__DIR__ .  '/../../../tools/s3-file-nester.php --action copy -vvv --perform', $returnVar);
exit($returnVar);
