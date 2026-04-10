<?php

/**
 * @author      Eli Lowinger
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education
 */

namespace Schoolbox\SaveData;

use Alaress_Date_Format;
use Aws\S3\S3Client;
use Aws\S3\Transfer;
use Exception;
use RuntimeException;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\S3\Migration;
use Schoolbox\Version\Version;

/**
 * CLI Tool
 *
 * Prepares database and storage as a dataset tarball and optionally uploads it to s3
 */
class Cli extends App
{
    private const OPT_LONG_HELP = "help";
    private const OPT_LONG_NAME = "name:";
    private const OPT_LONG_INITIAL = "initial";
    private const OPT_LONG_UPLOAD = "upload";
    private const OPT_SHORT_UPLOAD = 'u';
    private const OPT_SHORT_HELP = "h";
    private const OPT_SHORT_NAME = "n:";
    private const OPT_SHORT_INITIAL = "i";
    private const S3_BUCKET = 'schoolbox-data';

    private string $name;
    private bool $initial;
    private bool $upload;

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_NAME,
            self::OPT_LONG_INITIAL,
            self::OPT_LONG_UPLOAD,
        ];
    }

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_NAME,
            self::OPT_SHORT_INITIAL,
            self::OPT_SHORT_UPLOAD,
        ];
    }

    /**
     * @inheritdoc
     *
     */
    public function run(): void
    {
        $name = $this->getOpt(self::OPT_SHORT_NAME) ?? $this->getOpt(self::OPT_LONG_NAME);
        if ($this->getOpt(self::OPT_SHORT_HELP) || $this->getOpt(self::OPT_LONG_HELP)) {
            $this->help();
            exit;
        }
        if ($name === null) {
            $this->getLogger()->error("Name must be provided");
            $this->help();
            exit(1);
        }
        $this->initial = $this->getOpt(self::OPT_SHORT_INITIAL) || $this->getOpt(self::OPT_LONG_INITIAL);
        $this->upload = $this->getOpt(self::OPT_SHORT_UPLOAD) || $this->getOpt(self::OPT_LONG_UPLOAD);
        $this->name = $name;
        $site_type = $this->getConfig()->get(ConfigMapper::NAME_SITE_ENVIRONMENT);
        switch ($site_type) {
            case Version::SITE_ENVIRONMENT_CLOUD_SAAS:
                $this->cloud();
                break;
            case Version::SITE_ENVIRONMENT_CLIENT_HOSTED:
                $this->vagrant();
                break;
            default:
                $this->getLogger()->error("Invalid Site Environment!! You should never reach this error");
                exit(1);
        }
    }

    private function help(): void
    {
        echo <<<HELP
Saves the current instance as a dataset tarball
usage: tools/save-data/save-data.php -n/--name [-u/--upload] [-i--initial] [-h/--help]
        -n/--name   The name of the dataset
        -u/--upload If the dataset should be uploaded to s3
        -i/--initial Do additional cleanup for initial datasets

HELP;
    }

    private function vagrant(): void
    {
        $this->getLogger()->info("Running in vagrant mode");
        $root = SITE_root;
        exec("git -C $root describe --exact-match --tags > /dev/null 2>&1", $output, $result_code);
        if ($result_code !== 0) {
            throw new RuntimeException("This is not a git installation or a tag is not checked out");
        }
        $this->maintenanceMode(1);
        if ($this->initial) {
            $this->cleanForInitial();
        }
        $this->cleanDirs();
        try {
            $this->clearSecrets();
            $dir = dirname($this->getConfig()->get(ConfigMapper::NAME_BACKUP_DIR));
            $this->mysqlDump($dir);
        } catch (Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $this->restoreVagrant();
            $this->maintenanceMode(0);
            exit(1);
        }
        $this->restoreVagrant();
        $this->maintenanceMode(0);
        $name = SITE_root . "/vagrantcache/{$this->name}";
        $this->createTarball($dir, $name);
        if ($this->upload) {
            $this->uploadTarball("{$name}.tar.gz", false);
            $this->migrateData();
        }
    }

    private function cloud(): void
    {
        $this->getLogger()->info("Running in Cloud mode");
        if (!$this->getConfig()->get(ConfigMapper::NAME_DATASET_INSTANCE)) {
            $this->getLogger()->error("Instance is not a dataset template please set `" . ConfigMapper::NAME_DATASET_INSTANCE . "` in the config table");
            exit(1);
        }
        $instance_dir = dirname($this->getConfig()->get(ConfigMapper::NAME_BACKUP_DIR));
        $timestamp = Alaress_Date_Format::now()->toIso8601String();
        $backup_name = "dataset-backup-$timestamp.sql";
        $this->mysqlDump($instance_dir, $backup_name);
        $this->maintenanceMode(1);
        $tempdir = '/tmp/' . INSTANCE_ID . "-dataset";
        try {
            if (is_dir($tempdir)) {
                $this->deleteDir($tempdir);
            }
            mkdir($tempdir);
            if ($this->initial) {
                $this->cleanForInitial();
            }
            $this->downloadBucket($tempdir);
            $this->clearSecrets();
            $this->makeLocal();
            $this->mysqlDump($tempdir);
        } catch (Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $this->restoreBackup("$instance_dir/$backup_name");
            $this->maintenanceMode(0);
            exit(1);
        }
        $this->restoreBackup("$instance_dir/$backup_name");
        $this->maintenanceMode(0);
        $tarName = "/tmp/{$this->name}";
        $this->createTarball($tempdir, $tarName);
        if ($this->upload) {
            $this->uploadTarball("$tarName.tar.gz", false);
        }
    }

    private function maintenanceMode(int $enabled): void
    {
        $this->getLogger()->info("setting maintenance mode to be {$enabled}");
        $query = "UPDATE config set value = :value where name = :name LIMIT 1;";
        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        $statement->execute([
            "value" => $enabled,
            "name" => ConfigMapper::NAME_MAINTENANCE_MODE
        ]);
    }

    private function makeLocal(): void
    {
        $this->getLogger()->info("making DB local");
        $configs = [
            ConfigMapper::NAME_OTFS_ADAPTER => FilesystemFactory::FILE_SYSTEM_ADAPTER_LOCAL,
            ConfigMapper::NAME_DATASET_INSTANCE => 0
        ];
        $query = "UPDATE config set value = :value where name = :name limit 1;";
        $statement = $this->getDatabaseInternal()->prepareQuery($query);
        foreach ($configs as $name => $value) {
            $this->getLogger()->debug("$name => $value");
            $statement->execute([
                "value" => $value,
                "name" => $name
            ]);
        }
    }

    private function cleanForInitial(): void
    {
        $this->getLogger()->info("cleaning for initial dataset");
        $tables = [
            'cmsLog',
            'user_activity_log',
            'login_log',
            'msg_reroute_override',
            'user_assume_log',
            'user_password_reset_request',
            'ups_log'
        ];
        foreach ($tables as $table) {
            $query = "TRUNCATE $table";
            $statement = $this->getDatabaseInternal()->prepareQuery($query);
            $this->getLogger()->debug("truncating {$table}");
            $statement->execute();
        }
    }

    private function cleanDirs(): void
    {
        # no longer cleaning other dirs as we are only saving schoolbox.sql and storage/
        $this->getLogger()->info("cleaning log and cache dirs");
        exec(SITE_root . "/tools/clean-cache-dir.sh");
    }

    private function clearSecrets(): void
    {
        $this->getLogger()->info("cleaning secrets");
        $sql = file_get_contents(SITE_root . "/sql/vagrant-cleanup.sql");
        $this->getDatabaseInternal()->execute($sql);
        $this->getLogger()->info("cleaning transforms");
        $query = "DELETE st FROM storage_transform st LEFT JOIN storage s ON st.hash = s.hash WHERE s.storage_id IS NULL";
        $this->getDatabaseInternal()->execute($query);
    }

    private function mysqlDump(string $directory, string $filename = "schoolbox.sql"): void
    {
        if (!is_writable($directory)) {
            $this->getLogger()->error("{$directory} is not writable please run the following commands
                `sudo chown www-data:www-data {$directory}`
                `sudo chmod 775 {$directory}`");
            throw new RuntimeException("Permissions Error");
        }
        $this->getLogger()->info("dumping db to $directory/$filename");
        $dumpcmd = sprintf(
            "mariadb-dump --single-transaction -f --allow-keywords --default-character-set=utf8mb4 -i -e -q -a -c -h %s -u %s -p%s %s | sed -E \"s/^\/\*\!50013 DEFINER=.+\*\//\/\* Fallback to default DEFINER \*\//g\" > %s/%s",
            SQL_host,
            SQL_user,
            SQL_passwd,
            SQL_db,
            $directory,
            $filename
        );
        exec($dumpcmd, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException("mariadb-dump failed");
        }
    }

    private function restoreVagrant(): void
    {
        $this->getLogger()->info("restoring vagrant secrets");
        $sql = file_get_contents(SITE_root . "/sql/vagrant.sql");
        $this->getDatabaseInternal()->execute($sql);
        $sql = file_get_contents(SITE_root . "/vagrantcache/vagrant-secrets.sql");
        $this->getDatabaseInternal()->execute($sql);
    }

    private function restoreBackup(string $backup_path): void
    {
        $this->getLogger()->info("restoring from backup");
        $sql = file_get_contents($backup_path);
        $this->getDatabaseInternal()->execute($sql);
    }

    private function createTarball(string $directory, string $name): void
    {
        $this->getLogger()->info("creating {$name}.tar.gz from {$directory}");
        $cmd = sprintf(
            'cd %s &&'  .
                'find . -type f -print0 | ' .
                "tar --null --no-recursion -czf %s -C %s --transform 's,^./,data/,' -T -",
            escapeshellarg($directory),
            escapeshellarg("{$name}.tar.gz"),
            escapeshellarg($directory)
        );
        exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException($output[0]);
        }
    }

    private function uploadTarball(string $path, bool $deleteAfter): void
    {
        $this->getLogger()->info("uploading {$path}");
        # this is on echo not a logger as this needs to be a prompt for the user, so it needs to go to stdout not stderr
        echo "Please go to https://d-976758be37.awsapps.com/start/#/?tab=accounts and click on 'Access keys' on the vagrant role\n";
        $accessKey = $this->readLine("Access key ID:  ");
        $accessSecret = $this->readPassword("Secret Access Key:  ");
        $accessToken = $this->readPassword("Session Token:  ");

        $s3Client = new S3Client([
            'region' => 'ap-southeast-2',
            'credentials' => [
                'key'       => $accessKey,
                'secret'    => $accessSecret,
                'token'     => $accessToken
            ]
        ]);
        # line which will be overwritten by the uploading progress
        echo "uploading";
        $s3Client->putObject([
            'Bucket'        => self::S3_BUCKET,
            'Key'           => basename($path),
            'SourceFile'    => $path,
            'ACL'           => 'public-read',
            '@http'         => [
                'progress'  => function ($downloadTotalSize, $downloadSizeSoFar, $uploadTotalSize, $uploadSizeSoFar): void {
                    $total =  $this->formatBytes($uploadTotalSize);
                    $soFar = $this->formatBytes($uploadSizeSoFar);
                    echo "\rUploaded {$soFar} of {$total}";
                }
            ]
        ]);
        if ($deleteAfter) {
            $this->getLogger()->info("deleting {$path}");
            unlink($path);
        }
    }
    private function migrateData(): void
    {
        $migrateApp = new Migration();
        $migrateApp->setup(["p" => false, 'q' => false], $this->getDi(), $this->getOutStream(), $this->getLogger(), true);
        $migrateApp->run();
    }
    private function deleteDir(string $dir): void
    {
        $this->getLogger()->info("deleting $dir");
        exec("rm -r $dir");
    }
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $power = floor(($bytes ? log($bytes) : 0) / log(1024));
        $power = min($power, count($units) - 1);
        $bytes /= 1024 ** $power;
        return round($bytes, 2) . $units[$power];
    }
    private function downloadBucket(string $dir): void
    {
        $accessKey = $this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_CRED_KEY);
        $secretKey = $this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_CRED_SECRET);
        $region = $this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_REGION);
        $s3AltHost = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_HOST) ?? "");
        $s3AltBucket = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_BUCKET) ?? "");
        $s3AltAccessKey = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_ACCESS_KEY) ?? "");
        $s3AltSecretAccessKey = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_SECRET_KEY) ?? "");
        $s3AltRegion = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_ALT_REGION) ?? "");
        $s3Bucket = trim($this->getConfig()->get(ConfigMapper::NAME_OTFS_S3_BUCKET) ?? "");

        $cfg = [
            'region' => $region,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey
            ],
        ];

        if (!empty($s3AltHost)) {
            $cfg['endpoint'] = $s3AltHost;
            $cfg['credentials']['key'] = $s3AltAccessKey;
            $cfg['credentials']['secret'] = $s3AltSecretAccessKey;
            $cfg['region'] = $s3AltRegion;
        }

        $client = new S3Client($cfg);
        $finalBucket = !empty($s3AltHost) && !empty($s3AltBucket) ? $s3AltBucket : $s3Bucket;

        $bucketSize = $this->getBucketStorage($client, $finalBucket, 'storage/');
        $bucketSizeFormat = $this->formatBytes($bucketSize);
        $this->getLogger()->info("downloading bucket ($bucketSizeFormat)");
        $freeSpace = disk_free_space($dir);
        if ($freeSpace - $bucketSize <= 0) {
            throw new RuntimeException("not enough free space in disk: $freeSpace available, $bucketSize in s3 bucket");
        }
        $manager = new Transfer($client, "s3://$finalBucket/storage", "$dir/storage");
        $manager->transfer();
        $this->getLogger()->info("files downloaded");
    }
    private function getBucketStorage(S3Client $client, string $bucket, string $prefix): float
    {
        $size = 0;
        $objects = $client->getPaginator('ListObjectsV2', [
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);
        foreach ($objects as $res) {
            foreach ($res["Contents"] as $object) {
                $size += $object['Size'];
            }
        }

        return $size;
    }
}
