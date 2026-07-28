<?php

/**
 * @author      Eli Lowinger
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.education
 */

namespace Schoolbox\SaveData;

use Alaress_Date_Format;
use Aws\CommandPool;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Exception;
use RuntimeException;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\Resource\Storage\S3\Migration;
use Schoolbox\Version\Version;
use Schoolbox\Video\QueueMapper;
use Schoolbox\Video\ResourceVideo;
use Throwable;

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

    /** Number of storage objects to download from the bucket at a time. */
    private const DOWNLOAD_CONCURRENCY = 25;

    /** Transform suffixes persisted alongside a transcoded video or audio storage object. */
    private const VIDEO_TRANSFORMS = [
        ResourceVideo::TRANSCODE,
        ResourceVideo::POSTER,
        ResourceVideo::POSTER_LETTERBOX,
        ResourceVideo::THUMBNAIL,
    ];

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
        $tempdir = "$instance_dir/dataset-tmp";
        try {
            if (is_dir($tempdir)) {
                $this->deleteDir($tempdir);
            }
            mkdir($tempdir);
            if ($this->initial) {
                $this->cleanForInitial();
            }
            $missingFromS3 = $this->downloadBucket($tempdir);
            $this->clearSecrets();
            $this->clearTransforms();
            $this->makeLocal();
            $this->mysqlDump($tempdir);
        } catch (Exception $e) {
            $this->getLogger()->error($e->getMessage());
            $this->restoreBackup("$instance_dir/$backup_name");
            $this->maintenanceMode(0);
            $this->deleteDir($tempdir);
            exit(1);
        }
        $this->restoreBackup("$instance_dir/$backup_name");
        $this->maintenanceMode(0);
        $tarName = "$instance_dir/{$this->name}";
        $this->createTarball($tempdir, $tarName);
        $this->deleteDir($tempdir);
        if ($this->upload) {
            $this->uploadTarball("$tarName.tar.gz", false);
            unlink("$tarName.tar.gz");
        }
        foreach ($missingFromS3 as $hash) {
            echo "WARNING: hash $hash exists in storage table but was not found in S3" . PHP_EOL;
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
            ConfigMapper::NAME_DATASET_INSTANCE => 0,
            ConfigMapper::NAME_OTFS_S3_CRED_KEY => '',
            ConfigMapper::NAME_OTFS_S3_CRED_SECRET => '',
            ConfigMapper::NAME_OTFS_S3_BUCKET => '',
            ConfigMapper::NAME_OTFS_S3_REGION => '',
            ConfigMapper::NAME_OTFS_S3_ALT_HOST => '',
            ConfigMapper::NAME_OTFS_S3_ALT_ACCESS_KEY => '',
            ConfigMapper::NAME_OTFS_S3_ALT_SECRET_KEY => '',
            ConfigMapper::NAME_OTFS_S3_ALT_BUCKET => '',
            ConfigMapper::NAME_OTFS_S3_ALT_REGION => '',
            ConfigMapper::NAME_CLAMD_HOST => '',
            ConfigMapper::NAME_SITE_STATIC_ASSET_SERVE_FROM_CDN => 0,
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

    /**
     * Clears image transform metadata from the dataset.
     *
     * Transforms are persisted as `<hashed path>-<cache key>`, which the storage hash download does not reach,
     * so every row would otherwise point at a file absent from the dataset. They are regenerated on demand.
     *
     * Cloud mode only: the vagrant path archives the local storage tree, transform files included, and has no
     * full database restore to undo this on the source instance.
     */
    private function clearTransforms(): void
    {
        $this->getLogger()->info("clearing transforms");
        $this->getDatabaseInternal()->execute("TRUNCATE storage_transform");
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
        $cmd = sprintf(
            "mariadb -h %s -u %s -p%s %s < %s",
            SQL_host,
            SQL_user,
            SQL_passwd,
            SQL_db,
            escapeshellarg($backup_path)
        );
        exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException("restore failed");
        }
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
    private function downloadBucket(string $dir): array
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

        $query = $this->getDatabaseInternal()->prepareQuery("SELECT hash FROM storage");
        $query->execute();
        $hashes = $query->fetchColumnArray('hash');

        # Transcoded media is persisted alongside the original as `<hashed path>-<transform>`. Those objects are
        # not reachable from the storage hashes above, and a completed video_queue row is never picked up again by
        # QueueMapper::getPending(), so they have to be fetched or the media cannot play once provisioned.
        $query = $this->getDatabaseInternal()->prepareQuery(
            "SELECT hash FROM video_queue WHERE status = :status"
        );
        $query->execute(["status" => QueueMapper::STATUS_COMPLETED]);
        $transcodedHashes = $query->fetchColumnArray('hash');

        # Size the download from the objects actually wanted: the bucket may hold objects the storage table no
        # longer references, and totalling those would fail the free space check over files never downloaded.
        $objectSizes = $this->getBucketStorage($client, $finalBucket, 'storage/');

        # Absence of a storage object is reported, while absence of a transform is not: not every transform
        # exists for every transcode.
        $wanted = [];
        foreach ($hashes as $hash) {
            $wanted[] = ['path' => FilesystemProxy::getPathFromHash($hash), 'reportIfMissing' => $hash];
        }
        foreach ($transcodedHashes as $hash) {
            $relativePath = FilesystemProxy::getPathFromHash($hash);
            foreach (self::VIDEO_TRANSFORMS as $transform) {
                $wanted[] = ['path' => "$relativePath-$transform", 'reportIfMissing' => null];
            }
        }

        $downloadSize = 0;
        foreach ($wanted as $object) {
            $downloadSize += $objectSizes["storage/{$object['path']}"] ?? 0;
        }
        $downloadSizeFormat = $this->formatBytes($downloadSize);
        $this->getLogger()->info("downloading bucket ($downloadSizeFormat)");
        $freeSpace = disk_free_space($dir);
        if ($freeSpace - $downloadSize <= 0) {
            throw new RuntimeException(
                "not enough free space in disk: $freeSpace available, $downloadSize to download from s3 bucket"
            );
        }

        # Absent keys are known from the listing, so they are never requested.
        $missing = [];
        $toDownload = [];
        foreach ($wanted as $object) {
            if (!isset($objectSizes["storage/{$object['path']}"])) {
                if ($object['reportIfMissing'] !== null) {
                    $missing[] = $object['reportIfMissing'];
                }
                continue;
            }
            $toDownload[] = $object['path'];
        }

        $this->downloadObjects($client, $finalBucket, $toDownload, $dir);
        $this->getLogger()->info("files downloaded");
        return $missing;
    }

    /**
     * Downloads objects from the storage prefix of the bucket.
     *
     * Downloads run concurrently: a dataset holds mostly small objects, so downloading them one at a time
     * accumulates a network round trip per object rather than being limited by bandwidth.
     *
     * @param string[] $relativePaths paths within the storage prefix, as returned by FilesystemProxy
     */
    private function downloadObjects(S3Client $client, string $bucket, array $relativePaths, string $dir): void
    {
        $commands = function () use ($client, $bucket, $relativePaths, $dir) {
            foreach ($relativePaths as $index => $relativePath) {
                $localPath = "$dir/storage/$relativePath";
                if (!is_dir(dirname($localPath))) {
                    mkdir(dirname($localPath), 0777, true);
                }
                yield $index => $client->getCommand('GetObject', [
                    'Bucket' => $bucket,
                    'Key'    => "storage/$relativePath",
                    'SaveAs' => $localPath,
                ]);
            }
        };

        $failures = [];
        $pool = new CommandPool($client, $commands(), [
            'concurrency' => self::DOWNLOAD_CONCURRENCY,
            'preserve_iterator_keys' => true,
            'rejected' => function (Throwable $reason, int $index) use (&$failures, $relativePaths): void {
                $failures[$relativePaths[$index]] = $reason instanceof AwsException
                    ? ($reason->getAwsErrorCode() ?? $reason->getMessage())
                    : $reason->getMessage();
            },
        ]);
        $pool->promise()->wait();

        if (!empty($failures)) {
            # These objects were in the listing, so a failure is not an absent file: fail the run rather than
            # packaging a dataset quietly short of the files its database references.
            $summary = [];
            foreach ($failures as $relativePath => $error) {
                $summary[] = "$relativePath ($error)";
            }
            throw new RuntimeException(
                sprintf('failed to download %d storage objects: %s', count($failures), implode(', ', $summary))
            );
        }
    }
    /**
     * Lists the objects under a prefix of the bucket.
     *
     * @return array<string, int> key: the object key, value: its size in bytes
     */
    private function getBucketStorage(S3Client $client, string $bucket, string $prefix): array
    {
        $sizes = [];
        $objects = $client->getPaginator('ListObjectsV2', [
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);
        foreach ($objects as $res) {
            foreach ($res["Contents"] ?? [] as $object) {
                $sizes[$object['Key']] = $object['Size'];
            }
        }

        return $sizes;
    }
}
