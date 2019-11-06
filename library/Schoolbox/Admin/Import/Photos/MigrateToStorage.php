<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Admin\Import\Photos;

use Alaress\IDatabase;
use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Storage\Mapper;

/**
 * Class MigrateToStorage
 *
 * Migrates a folder's files into storage, assigning them to existing users
 *
 * @package Schoolbox\Admin\Import\Photos
 */
class MigrateToStorage
{

    /**
     * @var IDatabase
     */
    private $db;


    /**
     * @var string
     */
    private $portraitPath;


    /**
     * @var Mapper
     */
    private $storageMapper;


    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * Only update users who haven't got a portrait hash
     *
     * @var bool
     */
    private $backfill = false;


    /**
     * @var bool
     */
    private $perform;


    /**
     * MigrateToStorage constructor.
     *
     * @param string          $portraitPath
     * @param IDatabase       $db
     * @param Mapper          $storageMapper
     * @param LoggerInterface $logger
     * @param bool            $perform
     */
    public function __construct(string $portraitPath, IDatabase $db, Mapper $storageMapper, LoggerInterface $logger, bool $perform)
    {
        $this->portraitPath = $portraitPath;
        $this->db = $db;
        $this->storageMapper = $storageMapper;
        $this->logger = $logger;
        $this->perform = $perform;
    }


    /**
     * Migrate files from the $portraitPath into storage, assigning them to users
     *
     * @throws \League\Flysystem\FileNotFoundException
     * @throws \PDOException
     */
    public function migrate(): void
    {

        /* Prepared statements
         */
        $selectResult = $this->db->prepare('
            SELECT id, synergy_id, firstname, lastname, portrait_hash
            FROM `user`
            WHERE synergy_id = :externalId
        ');

        $updateResult = $this->db->prepare('
            UPDATE `user`
            SET portrait_hash = :hash
            WHERE id = :id
        ');

        /* Schools upload pictures to this directory periodically
         * This tool shoves them into storage
         * This tool can be run repeatedly in cron to preserve schools' existing workflows
         */
        $dirhandle = opendir($this->portraitPath);

        while ($file = readdir($dirhandle)) {
            if (('.' === $file) || ('..' === $file)) {
                continue;
            }

            /* If it's an actual file, send it in
             */
            $info = pathinfo($file);
            $path = "{$this->portraitPath}/{$file}";

            /* Naming convention in schoolbox instructions suggests this as the externalId
             */
            $externalId = $info['filename'];

            /* Find them classically
             */
            $selectResult->execute([
                'externalId' => $externalId,
            ]);

            /* If a user was found, try to assign image
             * Also accommodate scenario where multiple user accounts have the same external id
             * (give them the same picture)
             */
            $found = false;

            while ($user = $selectResult->fetch(\PDO::FETCH_ASSOC)) {
                $found = true;

                /* If this user already has a portrait hash and we only want to backfill, skip
                 */
                if ($this->backfill && '' !== (string)$user['portrait_hash']) {
                    $this->logger->warning("{$user['firstname']} {$user['lastname']} (id: {$user['id']}, externalId: {$user['synergy_id']}) already has a portrait image {$user['portrait_hash']}; skipped");
                    continue;
                }

                try {

                    /* Perform only
                     */
                    if ($this->perform) {

                        /* Save it into brownhole
                         */
                        $portrait = $this->storageMapper->acceptStream($externalId . '.jpg', file_get_contents($path));

                        /* Update user with latest version
                         */
                        $updateResult->execute([
                            'hash' => $portrait->getHash(),
                            'id'   => $user['id']
                        ]);

                        $this->logger->info("{$user['firstname']} {$user['lastname']} (id: {$user['id']}, externalId: {$user['synergy_id']}) now has portrait image {$file} {$portrait->getHash()} ({$portrait->getSize()})");
                    } else {
                        $this->logger->info("{$user['firstname']} {$user['lastname']} (id: {$user['id']}, externalId: {$user['synergy_id']}) would have new portrait image {$file}");
                    }
                } catch (\Schoolbox\Mapper\Exception $e) {

                    /* Something died in storage
                     */
                    $this->logger->critical("External id {$externalId} not saved; Storage error");
                }
            }

            /* Remove file when done
             */
            if ($this->perform) {
                $this->logger->info("Removing {$path}");
                unlink($path);
            }

            /* User not found; noop
             */
            if (!$found) {
                $this->logger->warning("External id {$externalId} not found; skipped");
            }
        }
    }

    /**
     * @param bool $backfill
     * @return MigrateToStorage
     */
    public function setBackfill(bool $backfill): self
    {
        $this->backfill = $backfill;
        return $this;
    }
}
