#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    private const LOCK_FILE = '/tmp/.transform-cleanup.lock';

    public function runAsRoot(): ?bool
    {
        // We are deleting files from local file system
        return true;
    }

    public function run(): void
    {
        if (file_exists(self::LOCK_FILE)) {
            $this->getLogger()->notice("[Terminate] Unable to run this tool as another process is currently running.");
            exit;
        }

        $startDate = date("d/m/Y H:i:s");

        $this->createLockFile();
        $this->cleanup($this->getConfig()->get('storage_dir'));
        $this->releaseLockFile();

        $this->writeLog($startDate, date("d/m/Y H:i:s"));
    }

    /**
     * Get the list of files in storage directory. And delete orphaned transform files.
     * Transform filename format should conform with one of the ITransformFactory::recognisesCacheKey().
     * If it doesn't, we'll delete the transform file from disk and its record from storage_transform table.
     */
    private function cleanup($dir)
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
     * Create lock file for overlap protection.
     */
    private function createLockFile(): void
    {
        if ($this->perform()) {
            try {
                touch(self::LOCK_FILE);
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * Delete lock file once the job is done.
     */
    private function releaseLockFile()
    {
        if ($this->perform()) {
            try {
                unlink(self::LOCK_FILE);
            } catch (Exception $e) {
                throw new Exception($e->getMessage(), $e->getCode());
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
        $prefix = $this->perform() ? 'Actioned' : 'Dry run';

        try {
            file_put_contents(
                $this->getConfig()->get('logs_dir') . '/' . 'transform-cleanup.log',
                sprintf('[%s] Job ran from: %s to: %s' . PHP_EOL, $prefix, $startDate, $endDate),
                FILE_APPEND | LOCK_EX
            );
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
