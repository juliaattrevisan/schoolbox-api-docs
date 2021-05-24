<?php

/**
 * Migrate portraits on the local filesystem into Storage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

passthru(__DIR__ .  '/../../../tools/portrait/migrate.php -vvv', $returnVar);
exit($returnVar);
