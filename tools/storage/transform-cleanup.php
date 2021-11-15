#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    public const LOCK_NAME = 'transform_cleanup';

    public const DEFAULT_TIMEOUT = 604800;

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
        ];
    }

    public function run(): void
    {

        $timeout = $this->getOptAsInt('lock-timeout:', self::DEFAULT_TIMEOUT);

        if ($this->getOpt('help')) {
            echo <<<HELP

Cleanup orphaned transform files.

Usage: transform-cleanup.php [--perform]
        --perform   Persist changes
        --lock-timeout   The max amount of time (in seconds) to hold the lock for.
                         Defaults to {$timeout}


HELP;
            exit;
        }

        $lock = $this->acquireLock(self::LOCK_NAME, $timeout);

        if (!$lock->isAcquired()) {
            $this->getLogger()->notice("[Terminate] Unable to run this tool as another process is currently running.");
            exit;
        }

        $startDate = date("d/m/Y H:i:s");
        $this->cleanup($this->getConfig()->get('storage_dir'));
        $lock->release();

        $this->writeLog($startDate, date("d/m/Y H:i:s"));
    }

    /**
     * @param string $dir
     *
     * Get the list of files in storage directory. And delete orphaned transform files.
     * Transform filename format should conform with one of the ITransformFactory::recognisesCacheKey().
     * If it doesn't, we'll delete the transform file from disk and its record from storage_transform table.
     */
    private function cleanup(string $dir)
    {
        $directoryIterator = new DirectoryIterator($dir);

        /** @var DirectoryIterator $item */
        foreach ($directoryIterator as $item) {
            if (!$item->isDot()) {
                if ($item->isDir()) {
                    $this->cleanup("$dir/$item");
                } else {
                    if (
                        $this->isTransformFile($item) &&
                        !$this->isVideoTransform($item) &&
                        !$this->isValidTransformNameFormat($item)
                    ) {
                        $this->deleteRecordFromStorageTransformTable($item);
                        $this->deleteTransformFileFromDisk($item);
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
        $videoTransforms = [
            \Schoolbox\Video\ResourceVideo::POSTER,
            \Schoolbox\Video\ResourceVideo::THUMBNAIL,
            \Schoolbox\Video\ResourceVideo::TRANSCODE,
            ];

        foreach ($videoTransforms as $transform) {
            if ($this->getCacheKeyFromFileName($item->getFilename()) === $transform) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param DirectoryIterator $item
     * @return bool
     */
    private function isValidTransformNameFormat(DirectoryIterator $item): bool
    {
        /** @var ITransformFactory[] $transforms */
        $transforms = [
            new \Alaress\Image\Transformation\MyImage(),
            new \Alaress\Image\Transformation\Storage(),
            new \Alaress\Image\Transformation\Icon(),
            new \Alaress\Image\Transformation\Portrait(),
        ];

        /** @var \Alaress\Image\Transformation\ITransformFactory[] $transforms */
        foreach ($transforms as $transform) {
            if ($transform->recognisesCacheKey($this->getCacheKeyFromFileName($item->getFilename()))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $fileName
     * @return false|string
     */
    private function getCacheKeyFromFileName(string $fileName)
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

        $verb = $this->perform() ? "Deleted" : "Would delete";
        $this->getLogger()->notice(sprintf("$verb %s from storage_transform table.", $item->getFilename()));

        if ($this->perform()) {
            $this->getDatabaseInternal()->executeQuery(
                "DELETE FROM storage_transform WHERE hash = :hash AND transform = :transform",
                [
                    'hash'      => $fileName,
                    'transform' => $this->getCacheKeyFromFileName($fileName),
                ]
            );
        }
    }

    /**
     * @param DirectoryIterator $item
     */
    private function deleteTransformFileFromDisk(DirectoryIterator $item)
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
     *
     * @param string $startDate
     * @param string $endDate
     */
    private function writeLog(string $startDate, string $endDate)
    {
        if ($this->perform()) {
            try {
                file_put_contents(
                    $this->getConfig()->get('logs_dir') . '/' . 'transform-cleanup.log',
                    sprintf('Job ran from: %s to: %s' . PHP_EOL, $startDate, $endDate),
                    FILE_APPEND | LOCK_EX
                );
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
