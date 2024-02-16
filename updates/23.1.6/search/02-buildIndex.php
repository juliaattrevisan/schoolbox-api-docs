#!/usr/bin/php
<?php

/**
 * Rebuild the full search index with this update.
 *
 * (Should this happen in the background? Can it?)
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru('php ' . __DIR__ .  '/../../../tools/search/buildIndex.php --perform --clear', $resultCode);
exit($resultCode);
