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
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new Daemon\Kernel(
    'Redistribute portraits to multiple accounts',
    [
        Daemon\Kernel::OPTION_LEVEL  => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS => E_ALL,
    ]
);

/* Setup
 * Assign synergy_id 483 to multiple bros
 * Make someone without a hash the most recently updated bro
UPDATE `schoolbox`.`user` SET `synergy_id`='483', `updated_at`='2019-07-26 12:00:16' WHERE `id`='15';
UPDATE `schoolbox`.`user` SET `synergy_id`='483' WHERE `id`='16';
UPDATE `schoolbox`.`user` SET `synergy_id`='483' WHERE `id`='13';
UPDATE `schoolbox`.`user` SET `updated_at`='1970-01-01 11:00:00' WHERE `id`='16';
UPDATE `schoolbox`.`user` SET `synergy_id`='483', `updated_at`='1980-01-01 09:00:00' WHERE `id`='17';
 */

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

    /* Find all the users with multiple external ids
     * Order it by hash, updated_at
     * Group by synergy_id
     * Log it
     */

    /* For each external_id,
     * Find the most recent record
     * Get its hash
     * Find all the other records for this external id
     * Apply the hash to those records
     * Log it
     */
    $log->warning('Test Message.');
});
