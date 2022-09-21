#!/usr/bin/php
<?php

/**
 * Install the 21.1 tour to advertise new features to staff
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru('php ' . __DIR__ .  '/../../../tools/tour/install.php --perform ' . __DIR__ . '/221tour.yaml', $returnVar);
exit($returnVar);
