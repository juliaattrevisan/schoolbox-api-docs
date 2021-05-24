#!/usr/bin/php
<?php

/**
 * Generate key pairs for JWT.
 *
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

passthru(__DIR__ .  '/../../../tools/token/generateKeyPair.php', $returnVar);
exit($returnVar);
