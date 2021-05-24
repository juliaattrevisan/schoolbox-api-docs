#!/usr/bin/php
<?php

/**
 * Update the 20.2 tour to fix contents
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru('php ' . __DIR__ .  '/../../tools/tour/update.php --perform ' . __DIR__ . '/../20.2.beta2/202tour.yaml', $returnVar);
exit($returnVar);
