#!/usr/bin/php
<?php

/**
 * Build full search index on initial deployment.
 *
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru('php ' . __DIR__ .  '/../../../tools/search/buildIndex.php --perform', $resultCode);
exit($resultCode);
