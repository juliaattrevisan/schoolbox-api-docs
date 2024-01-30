#!/usr/bin/php
<?php

/**
 * Clear and rebuild file search index, to restore sub-types.
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru('php ' . __DIR__ .  '/../../../tools/search/buildIndex.php --perform --clear --type=file', $resultCode);
exit($resultCode);
