#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru('php ' . __DIR__ .  '/../../../tools/search/buildIndex.php --perform --clear --type=tile', $returnVar);
exit($returnVar);
