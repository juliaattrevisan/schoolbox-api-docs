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

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo <<<HELP

Redistribute portraits from one user account to all others that have the 
same external id (synergy_id) value. It takes the most recently updated 
account with a portrait hash and gives it to all the others.

Usage: redistribute_portrait.php


HELP;
            exit;
        }

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

        /* Find the most recently updated account that has a portrait_hash
         * This has will be redistributed to all the other accounts
         */
        $mostRecentPortraitStatement = $db->prepare('
            SELECT id, username, portrait_hash, updated_at 
            FROM `user`
            WHERE portrait_hash IS NOT NULL
            AND synergy_id = :externalId
            ORDER BY updated_at DESC
            LIMIT 1
        ');

        /* The affected users, for logging
         */
        $affectedUsersStatement = $db->prepare("
            SELECT GROUP_CONCAT(id, ' ', username SEPARATOR ', ') AS affectedUsers
            FROM `user`
            WHERE portrait_hash IS NOT NULL
            AND synergy_id = :externalId
            ORDER BY updated_at DESC
        ");

        /* Mass update users
         */
        $update = $db->prepare('
            UPDATE `user`
            SET portrait_hash = :portraitHash
            WHERE synergy_id = :externalId
        ');

        /* Go find 'em
         */
        $multipleAccountHoldersStatement->execute();

        foreach ($multipleAccountHoldersStatement as $multipleAccountHolder) {
            $mostRecentPortraitStatement->execute([
                'externalId' => $multipleAccountHolder['synergy_id']
            ]);

            $mostRecentPortrait = $mostRecentPortraitStatement->fetch(\PDO::FETCH_ASSOC);

            /* If an account exists that contains a redistribute portrait, do it
             * Apply the hash to all records with the same externalId
             *
             * Otherwise we can't do anything about it - none of their accounts have a picture
             */
            if ($mostRecentPortrait) {
                $affectedUsersStatement->execute([
                    'externalId' => $multipleAccountHolder['synergy_id']
                ]);

                $affectedUsers = $affectedUsersStatement->fetch(\PDO::FETCH_ASSOC)['affectedUsers'];

                /* Update those people
                 */
                if ($this->perform()) {
                    $update->execute([
                        'portraitHash' => $mostRecentPortrait['portrait_hash'],
                        'externalId' => $multipleAccountHolder['synergy_id']
                    ]);
                }

                /* Log it
                 */
                $this->getLogger()->info(sprintf(
                    'Assigning portrait hash %s from %s (externalId %s, user %s) to users (%s) %s',
                    $mostRecentPortrait['portrait_hash'],
                    $mostRecentPortrait['username'],
                    $multipleAccountHolder['synergy_id'],
                    $mostRecentPortrait['id'],
                    $affectedUsers,
                    "/portrait.php?id={$multipleAccountHolder['id']}&size=constrain200"
                ));
            }
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
