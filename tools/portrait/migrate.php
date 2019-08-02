#!/usr/bin/php
<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\Mapper;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Migrate portraits on the local filesystem into Storage
 */
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

Migrate portraits from filesystem portrait_dir into Storage and associate them
with schoolbox user accounts

Usage: migrate.php


HELP;
            exit;
        }

        $db = Database::getInstance();
        $storageMapper = Mapper::getInstance();

        /** @var IConfigLoader $conf */
        $conf  = $this->getConfig();

        /* Prepared statements
         */
        $selectResult = $db->prepare('
            SELECT id, synergy_id, firstname, lastname
            FROM `user`
            WHERE synergy_id = :externalId
            LIMIT 1
        ');

        $updateResult = $db->prepare('
            UPDATE `user`
            SET portrait_hash = :hash
            WHERE id = :id
        ');

        /* Schools upload pictures to this directory periodically
         * This tool shoves them into storage
         * This tool can be run repeatedly in cron to preserve schools' existing workflows
         */
        $portraitPath = $conf->get('portrait_dir');

        $dirhandle = opendir($portraitPath);

        while ($file = readdir($dirhandle)) {
            if (('.' === $file) || ('..' === $file)) {
                continue;
            }

            /* If it's an actual file, send it in
             */
            $info = pathinfo($file);

            /* Naming convention in schoolbox instructions suggests this as the externalId
             */
            $externalId = $info['filename'];

            /* Find them classically
             */
            $selectResult->execute([
                'externalId' => $externalId,
            ]);

            $user = $selectResult->fetch(\PDO::FETCH_ASSOC);

            /* If a user was found, try to assign image
             */
            if ($user) {

                try {
                    $path = "{$portraitPath}/{$file}";

                    /* Save it into brownhole
                     */
                    $portrait = $storageMapper->acceptStream($externalId . '.jpg', file_get_contents($path));

                    /* Update user with latest version
                     */
                    $updateResult->execute([
                        'hash' => $portrait->getHash(),
                        'id' => $user['id']
                    ]);

                    $this->getLogger()->info("{$user['firstname']} {$user['lastname']} (id: {$user['id']}, externalId: {$user['synergy_id']}) now has portrait image {$file} {$portrait->getHash()} ({$portrait->getSize()})");

                    /* Remove file when done
                     */
                    unlink($path);
                } catch (\Schoolbox\Mapper\Exception $e) {

                    /* Something died in storage
                     */
                    $this->getLogger()->critical("External id {$externalId} not saved; Storage error");
                }
            } else {

                /* User not found; noop
                 */
                $this->getLogger()->warning("External id {$externalId} not found; skipped");
            }
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
