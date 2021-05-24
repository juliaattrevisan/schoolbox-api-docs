#!/usr/bin/php
<?php

/**
 * This tool redistributes portrait pictures from one user account to all others that have
 * the same external id (synergy_id) value. It takes the most recently updated account with
 * a portrait hash and gives it to all the others.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

declare(strict_types=1);

passthru(__DIR__ .  '/../../tools/portrait/redistribute_portrait.php -vvv --perform', $returnVar);
exit($returnVar);
