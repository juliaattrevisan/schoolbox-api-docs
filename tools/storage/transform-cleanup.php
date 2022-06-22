#!/usr/bin/php
<?php

declare(strict_types=1);

use Alaress\Image\Transformation;
use Alaress\Image\Transformation\ITransformFactory;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as AwsS3Adapter;
use League\Flysystem\Filesystem;
use Schoolbox\Application\CliTool;
use Schoolbox\Application\CliTool\AwsS3AppTrait;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Video\ResourceVideo;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    use AwsS3AppTrait;

    public const LOCK_NAME = 'transform_cleanup';

    public const DEFAULT_TIMEOUT = 604800;

    /**
     * All image/video transforms found on disk
     *
     * @var string[]
     */
    private array $validTransforms = [];

    private ConfigMapper $configMapper;

    private const VIDEO_TRANSFORMS = [
        ResourceVideo::POSTER,
        ResourceVideo::THUMBNAIL,
        ResourceVideo::TRANSCODE,
    ];

    /** @var ITransformFactory[] */
    private array $imageTransforms;

    private int $countOrphanTransformsDeleted     = 0;
    private int $sizeStorageRecovered             = 0;
    private int $countEmptyDirectoriesDeleted     = 0;
    private int $countStorageTransformRowsDeleted = 0;

    /**
     * Configured local storage directory. No trailing slash.
     *
     * @var string
     */
    private string $storageDir;

    private Filesystem $fileSystemAwsS3;


    public function __construct()
    {
        $this->configMapper    = ConfigMapper::getInstance();
        $this->imageTransforms = [
            new Transformation\MyImage(),
            new Transformation\Storage(),
            new Transformation\Icon(),
            new Transformation\Portrait(),
        ];
    }

    public function runAsRoot(): ?bool
    {
        // We are deleting files from local file system
        return true;
    }

    public function getLongOpts(): array
    {
        return [
            'help',
            'lock-timeout:',
            'force',
        ];
    }

    public function run(): void
    {
        $timeout = $this->getOptAsInt('lock-timeout:', self::DEFAULT_TIMEOUT);

        if ($this->getOpt('help')) {
            $this->printHelp();
            exit;
        }

        if ($this->hasToolCompleted() && !$this->getOpt('force')) {
            $this->getLogger()->notice("[Terminated] This tool has already been executed. You may add --force flag to override.");
            exit;
        }

        $lock = $this->acquireLock(self::LOCK_NAME, $timeout);

        if (!$lock->isAcquired()) {
            $this->getLogger()->notice("[Terminated] Unable to run this tool as another process is currently running.");
            exit;
        }
        $this->storageDir = rtrim($this->getConfig()->get('storage_dir'), DIRECTORY_SEPARATOR);

        if ($this->isUsingS3()) {
            $this->cleanUpS3();
        } else {
            $this->cleanupStorageDirectory($this->getConfig()->get('storage_dir'));
        }

        $this->cleanupStorageTransformTable();
        $lock->release();
        $this->registerToolCompletion();
        $this->printResults();
    }


    /**
     * Returns whether instance is using AWS S3 filesystem
     *
     * @return bool
     */
    private function isUsingS3(): bool
    {
        return $this->getConfig()->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3;
    }


    /**
     * Print Help message
     */
    private function printHelp(): void
    {
        $default = self::DEFAULT_TIMEOUT;
        echo <<<HELP

Cleanup orphaned transform files.

Usage: transform-cleanup.php [--perform]
        --perform   Persist changes
        --lock-timeout   The max amount of time (in seconds) to hold the lock for.
                         Defaults to $default
        --force This tool is meant to run one times a day. Adding this arg will let the operator re-run the this tool.

HELP;
    }


    /**
     * Returns whether this tool has previously successfully completed
     *
     * @return bool
     */
    private function hasToolCompleted(): bool
    {
        return $this->getConfig()->get('transform_cleanup_completed');
    }

    private function cleanUpS3(): void
    {
        $client                = $this->buildS3ClientOrExit($this->getLogger(), $this->getConfig());
        $s3Bucket              = trim($this->getConfig()->get('otfs_s3_bucket') ?? "");
        $filesInS3             = $this->getFilesInS3($this->getLogger(), $client, $s3Bucket);
        $this->fileSystemAwsS3 = new Filesystem(new AwsS3Adapter($client, $s3Bucket, FilesystemFactory::PREFIX_STORAGE));

        foreach ($filesInS3 as $key => $_v) {
            $path     = preg_replace('#^storage/#', '', $key);
            $fileName = basename($path);
            if ($this->needsToBeRetained($fileName)) {
                continue;
            }
            $this->deleteRecordFromStorageTransformTable($fileName);
            $this->deleteTransformFileFromDisk($path, $this->fileSystemAwsS3->fileSize($path));
        }
    }

    /**
     * Returns whether the specified file should be deleted
     *
     * @param string $fileName
     * @return bool
     */
    private function needsToBeRetained(string $fileName): bool
    {
        if (!$this->isTransformFile($fileName)) {
            $this->getLogger()->info("Retaining storage file: '$fileName'");
            return true;
        }
        if ($this->isVideoTransform($fileName)) {
            $this->getLogger()->info("Retaining video transform: '$fileName'");
            $this->validTransforms[] = $fileName;
            return true;
        }
        if ($this->isValidImageTransformNameFormat($fileName)) {
            $this->getLogger()->info("Retaining image transform: '$fileName'");
            $this->validTransforms[] = $fileName;
            return true;
        }
        return false;
    }

    /**
     * Get the list of files in storage directory. And delete orphaned transform files.
     * Transform filename format should conform with one of the ITransformFactory::recognisesCacheKey().
     * If it doesn't, we'll delete the transform file from disk and its record from storage_transform table.
     *
     * @param string $dir
     */
    private function cleanupStorageDirectory(string $dir): void
    {
        $directoryIterator = new DirectoryIterator($dir);

        /** @var DirectoryIterator $item */
        foreach ($directoryIterator as $item) {
            if ($item->isDot()) {
                continue;
            }
            if ($item->isDir()) {
                $this->cleanupStorageDirectory("$dir/$item");
                $this->cleanupEmptyDirectory($item);
            } else {
                $fileName = $item->getFilename();
                if ($this->needsToBeRetained($fileName)) {
                    continue;
                }
                $this->deleteRecordFromStorageTransformTable($fileName);

                $this->deleteTransformFileFromDisk(
                    str_replace("$this->storageDir/", '', $item->getPathname()),
                    $item->getSize()
                );
            }
        }
    }

    /**
     * @param string $fileName
     * @return bool
     */
    private function isTransformFile(string $fileName): bool
    {
        return strpos($fileName, '-') !== false;
    }

    /**
     * @param string $fileName
     * @return bool
     */
    private function isVideoTransform(string $fileName): bool
    {
        return in_array($this->getCacheKeyFromFileName($fileName), self::VIDEO_TRANSFORMS, true);
    }

    /**
     * @param string $fileName
     * @return bool
     */
    private function isValidImageTransformNameFormat(string $fileName): bool
    {
        $cacheKey = $this->getCacheKeyFromFileName($fileName);
        foreach ($this->imageTransforms as $transform) {
            if ($transform->recognisesCacheKey($cacheKey)) {
                $this->getLogger()->info("Image Transform recognised: '$cacheKey' ({$transform::getSlug()})");
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $fileName
     * @return false|string
     */
    private function extractFileHashOnly(string $fileName)
    {
        return strtok($fileName, '-');
    }

    /**
     * @param string $fileName
     * @return string
     */
    private function getCacheKeyFromFileName(string $fileName): string
    {
        return substr(strstr($fileName, '-'), 1);
    }

    /**
     * @param string $fileName
     */
    private function deleteRecordFromStorageTransformTable(string $fileName): void
    {
        $this->deleteStorageTransformDataByHashAndCacheKey(
            $this->extractFileHashOnly($fileName),
            $this->getCacheKeyFromFileName($fileName),
        );
    }

    /**
     * @param string $relativePath
     * @param int    $size
     */
    private function deleteTransformFileFromDisk(string $relativePath, int $size): void
    {
        $fileName = basename($relativePath);
        $verb     = $this->perform() ? "Deleted" : "Would delete";
        $this->getLogger()->notice(sprintf("$verb %s from disk.", $fileName));

        $this->countOrphanTransformsDeleted++;
        $this->sizeStorageRecovered += $size;
        if ($this->perform()) {
            try {
                if ($this->isUsingS3()) {
                    $this->fileSystemAwsS3->delete($relativePath);
                } else {
                    unlink($this->storageDir . "/" . $relativePath);
                }
            } catch (Exception $e) {
                $this->getLogger()->error("Unable to delete " . $fileName . "\n" . $e->getMessage());
            }
        }
    }

    /**
     * Create log file in instance logs directory to indicate that the tool ran.
     */
    private function registerToolCompletion(): void
    {
        if ($this->perform()) {
            $config = $this->configMapper->findOneByColumn('name', 'transform_cleanup_completed');
            $config->setValue('1');
            $this->configMapper->save($config);
        }
    }

    /**
     * @param DirectoryIterator $item
     */
    private function cleanupEmptyDirectory(DirectoryIterator $item): void
    {
        if ($this->dirIsEmpty($item->getRealPath())) {
            $verb = $this->perform() ? "Deleted" : "Would delete";
            $this->getLogger()->notice(sprintf("$verb %s empty directory from disk.", $item->getRealPath()));
            $this->countEmptyDirectoriesDeleted++;
            $this->sizeStorageRecovered += $item->getSize();
            if ($this->perform()) {
                rmdir($item->getRealPath());
            }
        } else {
            $this->getLogger()->debug("Retaining non-empty dir: {$item->getRealPath()}");
        }
    }

    /**
     * @param string $pathName
     * @return bool
     */
    private function dirIsEmpty(string $pathName): bool
    {
        $handle = opendir($pathName);
        while (false !== ($entry = readdir($handle))) {
            if ($entry !== "." && $entry !== "..") {
                closedir($handle);

                return false;
            }
        }

        closedir($handle);
        return true;
    }

    /**
     * Remove records in storage_transform table where transform is no longer available from disk.
     */
    private function cleanupStorageTransformTable(): void
    {
        $this->getLogger()->notice("Cleanup storage_transform table for transforms that don't exist on disk.");
        foreach ($this->getStorageTransforms() as $transform) {
            if (!in_array($transform, $this->validTransforms, true)) {
                $this->deleteStorageTransformDataByHashAndCacheKey(
                    $this->extractFileHashOnly($transform),
                    $this->getCacheKeyFromFileName($transform),
                );
            }
        }
    }

    /**
     * @param string $hash
     * @param string $cacheKey
     */
    private function deleteStorageTransformDataByHashAndCacheKey(string $hash, string $cacheKey): void
    {
        $verb = $this->perform() ? "Deleted" : "Would delete";
        $this->countStorageTransformRowsDeleted++;
        if ($this->perform()) {
            try {
                $this->getDatabaseInternal()->executeQuery(
                    "DELETE FROM storage_transform WHERE hash = :hash AND transform = :transform",
                    [
                        'hash'      => $hash,
                        'transform' => $cacheKey,
                    ]
                );
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }

        $this->getLogger()->notice(sprintf("$verb %s from storage_transform table.", $hash . '-' . $cacheKey));
    }

    /**
     * @return string[]
     */
    private function getStorageTransforms(): array
    {
        return $this->getDatabaseInternal()
                    ->executeQuery("SELECT CONCAT(hash, '-', transform) FROM storage_transform WHERE state = 'done'")
                    ->fetchAll(PDO::FETCH_COLUMN);
    }

    private function printResults(): void
    {
        $this->getLogger()->notice(sprintf("Storage deleted: %s B", number_format($this->sizeStorageRecovered)));
        $this->getLogger()->notice("Count orphan transforms deleted: {$this->countOrphanTransformsDeleted}");
        $this->getLogger()->notice("Count empty dirs deleted: {$this->countEmptyDirectoriesDeleted}");
        $this->getLogger()->notice("Count storage transform rows deleted: {$this->countStorageTransformRowsDeleted}");
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
