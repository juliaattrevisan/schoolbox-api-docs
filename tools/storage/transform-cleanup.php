#!/usr/bin/php
<?php

declare(strict_types=1);

use Alaress\Image\Transformation\ITransformFactory;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Video\ResourceVideo;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    public const LOCK_NAME = 'transform_cleanup';

    public const DEFAULT_TIMEOUT = 604800;

    private array $validTransforms = [];

    private ConfigMapper $configMapper;

    private const VIDEO_TRANSFORMS = [
        ResourceVideo::POSTER,
        ResourceVideo::THUMBNAIL,
        ResourceVideo::TRANSCODE,
    ];

    /** @var ITransformFactory[]  */
    private array $imageTransforms;

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
        $default = self::DEFAULT_TIMEOUT;

        if ($this->getOpt('help')) {
            echo <<<HELP

Cleanup orphaned transform files.

Usage: transform-cleanup.php [--perform]
        --perform   Persist changes
        --lock-timeout   The max amount of time (in seconds) to hold the lock for.
                         Defaults to {$default}
        --force This tool is meant to run one times a day. Adding this arg will let the operator re-run the this tool.

HELP;
            exit;
        }
        $this->configMapper = ConfigMapper::getInstance();
        $this->imageTransforms = [
            new \Alaress\Image\Transformation\MyImage(),
            new \Alaress\Image\Transformation\Storage(),
            new \Alaress\Image\Transformation\Icon(),
            new \Alaress\Image\Transformation\Portrait(),
        ];

        if ($this->hasToolCompleted() && !$this->getOpt('force')) {
            $this->getLogger()->notice("[Terminated] This tool has already been executed. You may add --force flag to override.");
            exit;
        }

        $lock = $this->acquireLock(self::LOCK_NAME, $timeout);

        if (!$lock->isAcquired()) {
            $this->getLogger()->notice("[Terminated] Unable to run this tool as another process is currently running.");
            exit;
        }

        $this->cleanupStorageDirectory($this->getConfig()->get('storage_dir'));
        $this->cleanupStorageTransformTable();
        $lock->release();

        $this->registerToolCompletion();
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

    /**
     * @param string $dir
     *
     * Get the list of files in storage directory. And delete orphaned transform files.
     * Transform filename format should conform with one of the ITransformFactory::recognisesCacheKey().
     * If it doesn't, we'll delete the transform file from disk and its record from storage_transform table.
     */
    private function cleanupStorageDirectory(string $dir): void
    {
        $directoryIterator = new DirectoryIterator($dir);

        /** @var DirectoryIterator $item */
        foreach ($directoryIterator as $item) {
            if (!$item->isDot()) {
                if ($item->isDir()) {
                    $this->cleanupStorageDirectory("$dir/$item");
                    $this->cleanupEmptyDirectory($item);
                } else {
                    if (
                        $this->isTransformFile($item) &&
                        !$this->isVideoTransform($item) &&
                        !$this->isValidTransformNameFormat($item)
                    ) {
                        $this->deleteRecordFromStorageTransformTable($item);
                        $this->deleteTransformFileFromDisk($item);
                    }

                    // Save the list of valid transform files in memory for later.
                    if ($this->isTransformFile($item) &&
                        !$this->isVideoTransform($item) &&
                        $this->isValidTransformNameFormat($item)) {
                        $this->validTransforms[] = $item->getFilename();
                    }
                }
            }
        }
    }

    /**
     * @param DirectoryIterator $item
     * @return bool
     */
    private function isTransformFile(DirectoryIterator $item): bool
    {
        return strpos($item->getFilename(), '-') !== false;
    }

    /**
     * @param DirectoryIterator $item
     * @return bool
     */
    private function isVideoTransform(DirectoryIterator $item): bool
    {
        return in_array($this->getCacheKeyFromFileName($item->getFilename()), self::VIDEO_TRANSFORMS, true);
    }

    /**
     * @param DirectoryIterator $item
     * @return bool
     */
    private function isValidTransformNameFormat(DirectoryIterator $item): bool
    {
        $cacheKey = $this->getCacheKeyFromFileName($item->getFilename());
        foreach ($this->imageTransforms as $transform) {
            if ($transform->recognisesCacheKey($cacheKey)) {
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
     * @param DirectoryIterator $item
     * @return void
     */
    private function deleteRecordFromStorageTransformTable(DirectoryIterator $item): void
    {
        $fileName = $item->getFilename();

        $this->deleteStorageTransformDataByHashAndCacheKey(
            $this->extractFileHashOnly($fileName),
            $this->getCacheKeyFromFileName($fileName),
        );
    }

    /**
     * @param DirectoryIterator $item
     */
    private function deleteTransformFileFromDisk(DirectoryIterator $item): void
    {
        $verb = $this->perform() ? "Deleted" : "Would delete";
        $this->getLogger()->notice(sprintf("$verb %s from disk.", $item->getFilename()));

        if ($this->perform()) {
            try {
                unlink($item->getPathInfo() . '/' . $item->getFilename());
            } catch (Exception $e) {
                $this->getLogger()->error("Unable to delete " . $item->getFilename() . "\n" . $e->getMessage());
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
        if ($item->isDir() && $this->dirIsEmpty($item->getRealPath())) {
            $verb = $this->perform() ? "Deleted" : "Would delete";
            $this->getLogger()->notice(sprintf("$verb %s empty directory from disk.", $item->getRealPath()));

            if ($this->perform()) {
                rmdir($item->getRealPath());
            }
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

        if ($this->perform()) {
            try {
                $this->getDatabaseInternal()->executeQuery(
                    "DELETE FROM storage_transform WHERE hash = :hash AND transform = :transform",
                    [
                        'hash' => $hash,
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
     * @return array
     */
    private function getStorageTransforms(): array
    {
        return $this->getDatabaseInternal()
                    ->executeQuery("SELECT CONCAT(hash, '-', transform) FROM storage_transform")
                    ->fetchAll(PDO::FETCH_COLUMN);
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
