#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Aws\S3\S3Client;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\S3\DisasterRecovery;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Moves all S3 Disaster Recovery backups to an instance-specific dir to avoid collisions
 */
$app = new class extends CliTool\App {

    /** Hands off execution over to the App */
    public function run(): void
    {
        $bucketName = trim($this->getConfig()->get('otfs_s3_bucket') ?? "");
        $s3Client   = $this->buildS3ClientOrExit($bucketName);

        foreach (["backups/", "storage/backups/"] as $oldPrefix) {
            $this->migrateBackups($s3Client, $bucketName, $oldPrefix);
        }
    }


    /**
     * Builds and returns an S3Client or exits
     *
     * @param string $bucketName
     * @return S3Client
     */
    private function buildS3ClientOrExit(string $bucketName): S3Client
    {
        $s3AccessKey = trim($this->getConfig()->get('otfs_s3_cred_key') ?? "");
        $s3SecretKey = trim($this->getConfig()->get('otfs_s3_cred_secret') ?? "");
        $s3Region    = trim($this->getConfig()->get('otfs_s3_region') ?? "");
        $s3Version   = trim($this->getConfig()->get('otfs_s3_version') ?? "");


        $badConfig = empty($s3AccessKey) || empty($s3SecretKey) || empty($s3Region) || empty($s3Version) || empty($bucketName);

        if ($badConfig) {
            $this->getLogger()->notice("Insufficient S3 config to perform migration");
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }

        return new S3Client([
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
        ]);
    }


    /**
     * Migrate backups from old locations to new canonical location
     *
     * @param S3Client $s3Client
     * @param string   $bucketName
     * @param string   $oldPrefix
     */
    private function migrateBackups(S3Client $s3Client, string $bucketName, string $oldPrefix): void
    {
        $this->getLogger()->notice("Searching old prefix: '$oldPrefix'");
        $iterator  = $s3Client->getIterator('ListObjects', ['Bucket' => $bucketName, 'Prefix' => $oldPrefix]);
        $newPrefix = DisasterRecovery::PREFIX_BACKUPS;
        foreach ($iterator as $i => $object) {
            $oldKey  = $object['Key'];
            $matches = null;
            if (preg_match("#{$newPrefix}\d{4}-\d{2}-\d{2}/#", $oldKey, $matches)) {
                $this->getLogger()->warning("Already migrated, skipping: $oldKey");
                continue;
            }
            if (!preg_match("#{$oldPrefix}\d{4}-\d{2}-\d{2}/#", $oldKey, $matches)) {
                $this->getLogger()->warning("Skipping unrecognised: $oldKey");
                continue;
            }

            $this->getLogger()->notice("Old Key: $oldKey");

            $newKey = str_replace($oldPrefix, DisasterRecovery::PREFIX_BACKUPS, $object['Key']);
            $this->getLogger()->notice("New Key: $newKey");

            $s3Client->copyObject([
                'CopySource' => "{$bucketName}/{$oldKey}",
                'Bucket'     => $bucketName,
                'Key'        => $newKey,
            ]);
            $s3Client->deleteObject([
                'Bucket' => $bucketName,
                'Key'    => $oldKey,
            ]);
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG]))->runApp();
