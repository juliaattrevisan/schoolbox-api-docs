#!/usr/bin/php
<?php

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Aws\S3\S3Client;
use Schoolbox\Config\ConfigMapper as ConfigMapper;
use Carbon\Carbon;

require_once __DIR__ . '/../conf/core/require.php';

$app    = new class extends CliTool\App {
    private string $s3CredKey;
    private string $s3CredSecret;
    private string $s3Region;
    private string $s3Version;
    private string $s3Bucket;
    private S3Client $client;
    private const WEEKLY_UPLOAD_DAY              = "Saturday";
    private const MONTHLY_UPLOAD_DAY             = "01";
    private const DAILY_PREFIX                   = "backups/daily/";
    private const WEEKLY_PREFIX                  = "backups/weekly/";
    private const MONTHLY_PREFIX                 = "backups/monthly/";
    private const DAILY_BACKUP_PRUNE_THRESHOLD   = "7";  // delete backups older than 7 days
    private const WEEKLY_BACKUP_PRUNE_THRESHOLD  = "56";  // delete backups older than 7 * 8 days
    private const MONTHLY_BACKUP_PRUNE_THRESHOLD = "180"; // delete backups older than 30 * 6 days

    private function setOTFSCredentials(): void
    {
        $this->s3CredKey    = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_CRED_KEY) ?? "");
        $this->s3CredSecret = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_CRED_SECRET) ?? "");
        $this->s3Region     = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_REGION) ?? "");
        $this->s3Version    = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_VERSION) ?? "");
        $this->s3Bucket     = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_BUCKET) ?? "");
        $altHost            = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_HOST) ?? "");
        $altBucket          = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_BUCKET) ?? "");

        if (!empty($altHost) && !empty($altBucket)) {
            $this->s3Bucket = $altBucket;
        }
    }

    private function buildS3Client(): void
    {
        $cfg = [
            'credentials' => [
                'key'    => $this->s3CredKey,
                'secret' => $this->s3CredSecret,
            ],
            'region'      => $this->s3Region,
            'version'     => $this->s3Version,
        ];
        $altHost = trim($this->getConfig()->get('otfs_s3_alt_host') ?? "");
        $s3AltAccessKey = trim($this->getConfig()->get('otfs_s3_alt_access_key') ?? "");
        $s3AltSecretAccessKey = trim($this->getConfig()->get('otfs_s3_alt_secret_key') ?? "");
        $s3AltRegion = trim($this->getConfig()->get('otfs_s3_alt_region') ?? "");
        if (!empty($altHost)) {
            $cfg['endpoint'] = $altHost;
            $cfg['credentials']['key'] = $s3AltAccessKey;
            $cfg['credentials']['secret'] = $s3AltSecretAccessKey;
            $cfg['region'] = $s3AltRegion;
        }
        $this->client = new S3Client($cfg);
    }

    private function createBackupDirectoriesInS3(): void
    {
        $this->client->putObject([
            'Bucket' => $this->s3Bucket,
            'Key'    => self::DAILY_PREFIX,
            'Body'   => '',
        ]);
        $this->client->putObject([
            'Bucket' => $this->s3Bucket,
            'Key'    => self::WEEKLY_PREFIX,
            'Body'   => '',
        ]);
        $this->client->putObject([
            'Bucket' => $this->s3Bucket,
            'Key'    => self::MONTHLY_PREFIX,
            'Body'   => '',
        ]);
    }

    private function generateDailyBackup(): array
    {
        global $dbHost, $dbUser, $dbSchema, $dbPass;
        $filename = INSTANCE_ID . "_schoolbox_" . date("Y-m-d") . ".sql";
        $filepath = $this->getConfig()->get(ConfigMapper::NAME_BACKUP_DIR) . "/{$filename}";
        exec(
            "mariadb-dump --single-transaction -f --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -u {$dbUser} \
        -p{$dbPass} -h {$dbHost} {$dbSchema} > {$filepath}"
        );
        exec("gzip {$filepath}");
        return [$filename . ".gz", $filepath . ".gz"];
    }

    private function uploadFileToS3($key, $sourceFile): void
    {
        $this->client->putObject([
            'Bucket'     => $this->s3Bucket,
            'Key'        => $key,
            // e.g., backups/weekly/instanceId_schoolbox_2024-03-14.sql.gz
            'SourceFile' => $sourceFile,
            // e.g., /var/lib/schoolbox/instanceId/backups/weekly/instanceId_schoolbox_2024-03-14.sql.gz
        ]);
        // check if file upload was successful
        try {
            $fileInS3 = $this->client->getObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $key,
            ]);
        } catch (Aws\S3\Exception\S3Exception) {
            $this->getLogger()->error("Failed to back up database of instance " . INSTANCE_ID . " to S3.");
            exit(1);
        }
        if (strcmp(hash_file("md5", $sourceFile), str_replace('"', "", $fileInS3['ETag']))) {
            $this->getLogger()->error("Failed to back up database of instance " . INSTANCE_ID . " to S3.");
            exit(1);
        } else {
            $this->getLogger()->info("Backup uploaded to S3 successfully.");
        }
    }

    private function pruneOldBackups($prefix, $threshold): void
    {
        $cutoffDate = Carbon::today()->subtract($threshold, 'day')->toDateString();  // calculating cutoff day relative to current day
        $iterator    = $this->client->getIterator('ListObjects', [
            'Bucket' => $this->s3Bucket,
            'Prefix' => $prefix,
        ]);
        foreach ($iterator as $file) {
            if ($file['Key'] !== $prefix) {
                $backupDate = Carbon::parse($file['LastModified'])->toDateString();
                if ($backupDate < $cutoffDate) {
                    $this->getLogger()->info("Pruning old backup: {$file['Key']}.");
                    $this->client->deleteObject([
                        'Bucket' => $this->s3Bucket,
                        'Key'    => $file['Key'],
                    ]);
                }
            }
        }
    }

    public function run(): void
    {
        $options  = getopt("a", ["all"]);
        $backupAllInstances = array_key_exists('a', $options) || array_key_exists('all', $options);
        if (!$backupAllInstances) {
            $siteType        = trim($this->getConfig()->get(ConfigMapper::NAME_SITE_TYPE) ?? "");
            $siteEnvironment = trim($this->getConfig()->get(ConfigMapper::NAME_SITE_ENVIRONMENT) ?? "");
            if ($siteType !== "prod" or $siteEnvironment !== "cloud-saas") {
                $this->getLogger()->info("This script is configured to back up cloud production instances to S3.\nIf you wish to back up other instances, please re-run the script with the -a or --all flag.");
                exit(1);
            }
        }
        $this->setOTFSCredentials();
        $this->buildS3Client();
        $this->createBackupDirectoriesInS3();
        $this->getLogger()->info("Backing up MariaDB database for " . INSTANCE_ID);
        [$filename, $filepath] = $this->generateDailyBackup();
        $this->getLogger()->info("Uploading daily backup to S3 bucket.");
        $this->uploadFileToS3(self::DAILY_PREFIX . $filename, $filepath);
        if (date('l') === self::WEEKLY_UPLOAD_DAY) {
            $this->getLogger()->info("Uploading weekly backup to S3 bucket.");
            $this->uploadFileToS3(self::WEEKLY_PREFIX . $filename, $filepath);
        }
        if (date('d') === self::MONTHLY_UPLOAD_DAY) {
            $this->getLogger()->info("Uploading monthly backup to S3 bucket.");
            $this->uploadFileToS3(self::MONTHLY_PREFIX . $filename, $filepath);
        }
        // array of prefixes for S3 bucket and the pruning cutoff period
        $s3BackupThreshold = [
            self::DAILY_PREFIX   => self::DAILY_BACKUP_PRUNE_THRESHOLD,
            self::WEEKLY_PREFIX  => self::WEEKLY_BACKUP_PRUNE_THRESHOLD,
            self::MONTHLY_PREFIX => self::MONTHLY_BACKUP_PRUNE_THRESHOLD,
        ];
        foreach ($s3BackupThreshold as $prefix => $cutoff) {
            $this->pruneOldBackups($prefix, $cutoff);
        }
        unlink($filepath);  // cleanup backup from server
    }
};
$kernel = new CliTool\Kernel($app, [CliKernelAbstract::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
