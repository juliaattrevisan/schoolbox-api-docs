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
UPDATE `schoolbox`.`user` SET `portrait_hash`='0b107a8f53b04e45eb1ee5aa6eae14bd0454546b' WHERE `id`='17';
 */

$kernel->run(function ($di, $loop, LoggerInterface $log, $opts) {
    $db = Database::getInstance();

    /* Find all the users with multiple external ids
     */
    $multipleAccountHoldersStatement = $db->prepare('
        SELECT id, username, synergy_id
        FROM `user`
        WHERE synergy_id IS NOT NULL
        GROUP BY synergy_id
        HAVING (COUNT(*) > 1)
        ORDER BY id;
    ');

    $multipleAccountHoldersStatement->execute();

    /* Find the most recently updated account that has a portrait_hash
     * This has will be redistributed to all the other accounts
     */
    foreach ($multipleAccountHoldersStatement as $multipleAccountHolder) {
        $mostRecentPortraitStatement = $db->prepare('
            SELECT id, username, portrait_hash, updated_at 
            FROM `user`
            WHERE portrait_hash IS NOT NULL
            AND LENGTH(portrait_hash) > 0 
            AND synergy_id = :externalId
            ORDER BY updated_at DESC
            LIMIT 1
        ');

        $mostRecentPortraitStatement->execute([
            'externalId' => $multipleAccountHolder['synergy_id']
        ]);

        $mostRecentPortrait = $mostRecentPortraitStatement->fetch(\PDO::FETCH_ASSOC);

        /* Apply the hash to all records with the same externalId
         */
        $update = $db->prepare('
            UPDATE `user`
            SET portrait_hash = :portraitHash
            WHERE synergy_id = :externalId
        ');

        $update->execute([
            'portraitHash' => $mostRecentPortrait['portrait_hash'],
            'externalId' => $multipleAccountHolder['synergy_id']
        ]);

        /* Log it
         */
        $log->info(sprintf(
            'Assigning portrait hash %s from (externalId %s, user %s) to (externalId %s, user %s)',
            $mostRecentPortrait['portrait_hash'],
            $multipleAccountHolder['synergy_id'],
            $mostRecentPortrait['id'],
            $multipleAccountHolder['synergy_id'],
            $multipleAccountHolder['id']
        ));
    }
});
