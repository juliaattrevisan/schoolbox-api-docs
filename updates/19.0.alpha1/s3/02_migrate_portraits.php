<?php
/**
 * Migrate portraits on the local filesystem into Storage
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

passthru(__DIR__ .  '/../../../tools/portrait/migrate.php -vvv', $returnVar);
exit($returnVar);
