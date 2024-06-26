#!/usr/bin/php
<?php

/**
 * Run maxmarks.php to fix a problem with the max_mark value on shorttext quiz questions.
 * See maxmarks.php for more details.
 *
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru('php ' . __DIR__ .  '/../../../tools/learnosity/maxmarks.php --perform');
exit();
