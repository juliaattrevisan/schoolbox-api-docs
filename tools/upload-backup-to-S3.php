#!/usr/bin/php
<?php


use Schoolbox\Application\CliTool;
use Aws\S3\S3Client;

require_once __DIR__ . '/../conf/core/require.php';

$app = new class extends CliTool\App {

    private string $s3CredKey;
    private string $s3CredSecret;
    private string $s3Region;
    private string $s3Version;
    private string $s3Bucket;
    private S3Client $client;
    private const WEEKLY_UPLOAD_DAY = "Saturday";
    private const MONTHLY_UPLOAD_DAY = "1";
    private const DAILY_PREFIX = "backups/daily/";
    private const WEEKLY_PREFIX = "backups/weekly/";
    private const MONTHLY_PREFIX = "backups/monthly/";
    private const DAILY_BACKUP_PRUNE_THRESHOLD = " - 7  days";  // delete backups older than 7 days
    private const WEEKLY_BACKUP_PRUNE_THRESHOLD = " - 56 days";  // delete backups older than 7 * 8 days
    private const MONTHLY_BACKUP_PRUNE_THRESHOLD = " - 180 days"; // delete backups older than 30 * 6 days
    private function setOTFSCredentials(): void {
        $this->s3CredKey = trim($this->getConfig()->get('otfs_s3_cred_key') ?? "");
        $this->s3CredSecret = trim($this->getConfig()->get('otfs_s3_cred_secret') ?? "");
        $this->s3Region = trim($this->getConfig()->get('otfs_s3_region') ?? "");
        $this->s3Version = trim($this->getConfig()->get('otfs_s3_version') ?? "");
        $this->s3Bucket = trim($this->getConfig()->get('otfs_s3_bucket') ?? "");
    }
    private function buildS3Client(): void {
        $this->client = new S3Client([
                                         'credentials' => [
                                             'key'    => $this->s3CredKey,
                                             'secret' => $this->s3CredSecret,
                                         ],
                                         'region'      => $this->s3Region,
                                         'version'     => $this->s3Version,
                                     ]);
    }
    private function createBackupDirectoriesInS3(): void {
        $this->client->putObject([
                                     'Bucket' => $this->s3Bucket,
                                     'Key'    => self::DAILY_PREFIX,
                                     'Body'   => ''
                                 ]);
        $this->client->putObject([
                                     'Bucket' => $this->s3Bucket,
                                     'Key'    => self::WEEKLY_PREFIX,
                                     'Body'   => ''
                                 ]);
        $this->client->putObject([
                                     'Bucket' => $this->s3Bucket,
                                     'Key'    => self::MONTHLY_PREFIX,
                                     'Body'   => ''
                                 ]);
    }
    private function generateDailyBackup(): array {
        global $dbHost, $dbUser, $dbSchema, $dbPass;
        $filename = "schoolbox_" .  date("Y-m-d") . ".sql";
        $filepath = "/var/lib/schoolbox/" . getenv("INSTANCE_ID") . "/backups/" . $filename;
        exec("mysqldump -f --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -u {$dbUser} \
        -p{$dbPass} -h {$dbHost} {$dbSchema} > {$filepath}");
        exec("gzip {$filepath}");
        return array($filename . ".gz", $filepath . ".gz");
    }
    private function uploadFileToS3($key, $sourceFile): void {
        $this->client->putObject([
                                     'Bucket' => $this->s3Bucket,
                                     'Key' => $key,
                                     'SourceFile' => $sourceFile,
                                 ]);
        // check if file upload was successful
        try {
            $fileInS3 = $this->client->getObject([
                                                     'Bucket' => $this->s3Bucket,
                                                     'Key' => $key
                                                 ]);
        } catch (Aws\S3\Exception\S3Exception) {
            $this->getLogger()->error("Failed to back up database of instance " . getenv("INSTANCE_ID") . " to S3.");
            exit(1);
        }
        if (strcmp(hash_file("md5", $sourceFile), str_replace('"', "", $fileInS3['ETag']))) {
            $this->getLogger()->error("Failed to back up database of instance " . getenv("INSTANCE_ID") . " to S3.");
            exit(1);
        } else {
            $this->getLogger()->info("Backup uploaded to S3 successfully.");
        }
    }
    private function pruneOldBackups($prefix, $threshold): void {
        $cutoff_timestamp = strtotime(date("Y-m-d") . $threshold);  // calculating cutoff day relative to current day
        $iterator = $this->client->getIterator('ListObjects', array(
            'Bucket' => $this->s3Bucket,
            'Prefix' => $prefix,
        ));
        foreach ($iterator as $file) {
            if ($file['Key'] != $prefix) {
                $backup_timestamp = strtotime($file['LastModified']->format(DateTimeInterface::ISO8601_EXPANDED));
                if ($backup_timestamp < $cutoff_timestamp) {
                    $this->getLogger()->info("Pruning old backup: {$file['Key']}.");
                    $this->client->deleteObject(array(
                                                    'Bucket' => $this->s3Bucket,
                                                    'Key' => $file['Key']
                                                ));
                }
            }
        }
    }
    public function run(): void {
        $this->setOTFSCredentials();
        $this->buildS3Client();
        $this->createBackupDirectoriesInS3();
        $this->getLogger()->info("Backing up MySQL database for " . getenv("INSTANCE_ID"));
        list($filename, $filepath) = $this->generateDailyBackup();
        $this->getLogger()->info("Uploading daily backup to S3 bucket.");
        $this->uploadFileToS3(self::DAILY_PREFIX . $filename, $filepath);
        if (date('l') == self::WEEKLY_UPLOAD_DAY) {
            $this->getLogger()->info("Uploading weekly backup to S3 bucket.");
            $this->uploadFileToS3(self::WEEKLY_PREFIX . $filename, $filepath);
        };
        if (date('d') == self::MONTHLY_UPLOAD_DAY) {
            $this->getLogger()->info("Uploading monthly backup to S3 bucket.");
            $this->uploadFileToS3(self::MONTHLY_PREFIX . $filename, $filepath);
        };
        $s3BackupThreshold = array(
            self::DAILY_PREFIX => self::DAILY_BACKUP_PRUNE_THRESHOLD,
            self::WEEKLY_PREFIX => self::WEEKLY_BACKUP_PRUNE_THRESHOLD,
            self::MONTHLY_PREFIX => self::MONTHLY_BACKUP_PRUNE_THRESHOLD
        );
        foreach ($s3BackupThreshold as $prefix => $cutoff) {
            $this->pruneOldBackups($prefix, $cutoff);
        }
        unlink($filepath);  // cleanup backup from server
    }
};
$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
