<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Alaress_Byte_Format;
use Alaress\Database as AlaressDatabase;
use Aws\S3\Exception\S3Exception;
use InvalidArgumentException;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem;
use League\Flysystem\Filesystem;
use Monolog\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Application\CliTool\AwsS3AppTrait;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage;

/**
 * Migrates all files from local configured 'storage_dir' to AWS S3
 */
class Migration extends CliTool\App
{
    use AwsS3AppTrait;

    /**
     * Configured local storage directory. No trailing slash.
     *
     * @var string
     */
    private $storageDir;

    /** @var Alaress_Byte_Format */
    private $byteFormat;

    /** @var int */
    private $countStorageFilesUploaded;

    /** @var int */
    private $countTranscodeFilesUploaded;

    /** @var int */
    private $countBytesUploaded;

    /** @var int */
    private int $countUploadsFailed = 0;

    /**
     * Should we delete files from local storage?
     *
     * @var bool
     */
    private $deleteLocalCopy;

    /**
     * Indexed by filepath
     *
     * @var bool[]
     */
    private $filesInS3;

    /** @var bool */
    private $quiet;

    /** @var int */
    private int $maxUploadAttempts = 10; // TODO expose as CLI option

    /** @var array */
    private array $failedPaths = [];


    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['h', 'd', 'q'];
    }


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['help', 'delete', 'quiet'];
    }

    /** @var Filesystem */
    private $fileSystemAwsS3;

    /**
     * @inheritDoc
     * @throws AlaressDatabase\Exception
     * @throws Flysystem\FileExistsException
     * @throws InvalidArgumentException
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function run(): void
    {
        $this->getLogger()->info('S3 Storage Migration');

        if ($this->getOpt('h') || $this->getOpt('help')) {
            $this->printHelp();
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
        $this->quiet           = $this->getOpt('q') || $this->getOpt('quiet');
        $this->deleteLocalCopy = $this->getOpt('d') || $this->getOpt('delete');
        $level                 = $this->deleteLocalCopy ? Logger::WARNING : Logger::NOTICE;
        $this->getLogger()->log($level, ($this->deleteLocalCopy ? 'Deleting' : 'Keeping') . ' local storage files');

        $this->storageDir    = rtrim($this->getConfig()->get('storage_dir'), DIRECTORY_SEPARATOR);
        $this->byteFormat    = new Alaress_Byte_Format();

        $client                = $this->buildS3ClientOrExit($this->getLogger(), $this->getConfig());
        $s3Bucket              = trim($this->getConfig()->get('otfs_s3_bucket') ?? "");
        $this->filesInS3       = $this->getFilesInS3($this->getLogger(), $client, $s3Bucket);
        $this->fileSystemAwsS3 = new Filesystem(new AwsS3Adapter($client, $s3Bucket, FilesystemFactory::PREFIX_STORAGE));

        $this->migrateFiles();
        $this->listFailedMigrationPaths();
    }


    /**
     * Retrieves and migrates all local storage files to S3
     * @throws Flysystem\FileExistsException
     * @throws AlaressDatabase\Exception
     * @throws \Psr\Log\InvalidArgumentException
     */
    private function migrateFiles(): void
    {
        // Reset counts for logging
        $countFilesInS3        = $count = $this->countStorageFilesUploaded = $this->countTranscodeFilesUploaded = $this->countBytesUploaded = 0;
        $storageBeforeTransfer = $this->quiet ? null : $this->getDirectorySize($this->storageDir);

        // Retrieve and iterate all files referenced by `storage` table in database
        $this->getLogger()->notice("Fetching storage models from database, this may take a while on large datasets...");
        $files    = $this->getDatabaseInternal()->executeQuery("SELECT hash, filename, ext FROM storage")->fetchAll(\PDO::FETCH_ASSOC);
        $countAll = count($files);
        foreach ($files as $file) {
            $count++;
            $baseName = "{$file['filename']}.{$file['ext']}";
            $this->getLogger()->notice("Checking file $count/$countAll  {$file['hash']}, '$baseName'");
            if ($this->migrateFile($file['hash'])) {
                $countFilesInS3++;
            }
        }

        $remainingFiles = $this->quiet ? [] : $this->getRemainingFiles();
        if ($this->deleteLocalCopy && !$this->perform()) {
            $storageAfterTransfer = 0;
            // Directories also take up storage space
            $directories = [];
            if (!$this->quiet) {
                foreach ($remainingFiles as $remainingFile) {
                    $storageAfterTransfer += filesize($remainingFile);
                    $directories          = $this->addFileDirectoryAncestors($directories, $remainingFile);
                }
                foreach ($directories as $directory => $_) {
                    $storageAfterTransfer += shell_exec("stat -c%s $directory");
                }
            }
        } else {
            $storageAfterTransfer = $this->quiet ? null : $this->getDirectorySize($this->storageDir);
        }
        $this->getLogger()->notice(sprintf("Files in storage table:     %d", $countAll));
        $this->getLogger()->notice(sprintf("Files in s3 storage:        %d", count($this->filesInS3)));
        $this->getLogger()->notice(sprintf("Storage files uploaded:     %d", $this->countStorageFilesUploaded));
        $this->getLogger()->notice(sprintf("Transcode files uploaded:   %d", $this->countTranscodeFilesUploaded));
        $message = "Count failed uploads:       {$this->countUploadsFailed}";
        $this->countUploadsFailed ? $this->getLogger()->error($message) : $this->getLogger()->notice($message);
        $this->getLogger()->notice(sprintf("Data transferred:           %s", $this->byteFormat::bytesToString($this->countBytesUploaded)));
        if (!$this->quiet) {
            $this->getLogger()->notice(sprintf("Storage before transfer:    %s", $this->byteFormat::bytesToString($storageBeforeTransfer)));
            $this->getLogger()->notice(sprintf("Storage after transfer:     %s", $this->byteFormat::bytesToString($storageAfterTransfer)));
        }
        $this->getLogger()->notice(sprintf("Storage files in S3:        %d", $countFilesInS3));
        $countNotInS3 = $countAll - $countFilesInS3;
        $this->getLogger()->log($countNotInS3 ? Logger::WARNING : Logger::NOTICE, sprintf("Storage files not in S3:    %d", $countNotInS3));
        if (!$this->quiet) {
            $this->getLogger()->log(count($remainingFiles) ? Logger::WARNING : Logger::NOTICE, sprintf("Files in local storage but not in S3: %d", count($remainingFiles)));
            foreach ($remainingFiles as $remainingFile) {
                $this->getLogger()->debug($remainingFile);
            }
        }
    }


    /**
     * Adds all directories between storage root and specified file path
     *
     * @param bool[] $directories Indexed by directory name
     * @param string $filepath
     * @return bool[]
     */
    private function addFileDirectoryAncestors(array $directories, string $filepath): array
    {
        $levels = 1;
        while (true) {
            $dirname               = dirname($filepath, $levels);
            $directories[$dirname] = true;
            if ($dirname === $this->storageDir) {
                break;
            }
            $levels++;
        }
        return $directories;
    }


    /**
     * Returns the set of files remaining in the storage directory after running the tool.
     * Assumes that uploaded files will be deleted from local storage.
     *
     * @return string[]
     */
    private function getRemainingFiles(): array
    {
        $remainingFiles      = [];
        $filesInLocalStorage = array_filter(explode(PHP_EOL, shell_exec("find {$this->storageDir} -type f") ?? ''));
        $localStorageDir     = realpath($this->getConfig()->get('storage_dir'));

        foreach ($filesInLocalStorage as $fileInLocalStorage) {
            $path = str_replace($localStorageDir . '/', '', $fileInLocalStorage);
            if (array_key_exists(FilesystemFactory::PREFIX_STORAGE . $path, $this->filesInS3)) {
                continue;
            }
            $remainingFiles[] = $fileInLocalStorage;
        }
        return $remainingFiles;
    }


    /**
     * Returns a directory's size in bytes
     *
     * @param string $dirName
     * @return int
     */
    private function getDirectorySize(string $dirName): int
    {
        $this->getLogger()->info("Calculating local storage dir size. This may take a while...");
        return (int)shell_exec("du -sb {$dirName} | cut -f 1");
    }


    /**
     * Migrate a file from local storage to S3
     *
     * @param string $hash
     * @return bool Whether the file is now in S3 (either previously or newly uploaded)
     * @throws Flysystem\FileExistsException
     */
    private function migrateFile(string $hash): bool
    {
        $relativePath = Storage\Manager::getPathFromHash($hash);
        // Does file exist locally?
        $filepath     = "{$this->storageDir}/{$relativePath}";
        $foundLocally = file_exists($filepath);

        // Does file already exist in S3?
        $foundInS3 = array_key_exists(FilesystemFactory::PREFIX_STORAGE . $relativePath, $this->filesInS3);

        // Handle each of four possible scenarios re: file's presence on local and S3 filesystems:
        if (!$foundLocally) {
            if (!$foundInS3) {
                $this->getLogger()->warning("File not found locally or in S3, skipping");
            } else {
                $this->getLogger()->info("File not found locally but exists in S3, skipping");
            }
        } elseif (!$foundInS3) {
            $this->getLogger()->info("File on local disk but not in S3, uploading... (" . $this->byteFormat::bytesToString(filesize($filepath)) . ")");
            if ($this->moveFileToS3($filepath)) {
                $foundInS3 = true;
                $this->countStorageFilesUploaded++;
            } else {
                $this->countUploadsFailed++;
            }
        } else {
            $this->getLogger()->info('File both on local disk and in S3' . ($this->deleteLocalCopy ? ', deleting local copy' : ''));
            if ($this->deleteLocalCopy && $this->perform()) {
                unlink($filepath);
            }
        }

        $this->moveTranscodeFilesToS3($filepath);
        $this->deleteEmptyDirectoryAncestors($filepath);

        return $foundInS3;
    }


    /**
     * Moves file from local to S3 filesystem, deleting local copy
     *
     * @param string $filepathLocalAbsolute
     * @return bool Whether the move was successful
     * @throws Flysystem\FileExistsException
     */
    private function moveFileToS3(string $filepathLocalAbsolute): bool
    {
        $relativeLocalPath = $this->getRelativeLocalPath($filepathLocalAbsolute);

        try {
            $fp = fopen($filepathLocalAbsolute, 'rb');
        } catch (\Exception $e) {
            $this->failedPaths[] = $relativeLocalPath;
            $this->getLogger()->error($e->getMessage());

            return false;
        }

        if (!is_resource($fp)) {
            $this->failedPaths[] = $relativeLocalPath;
            $this->getLogger()->error("Cannot open file for reading, skipping");
            return false;
        }

        if ($this->perform()) {
            $writeSucceeded = false;
            try {
                $countUploadAttempts = 0;
                while (!$writeSucceeded && $countUploadAttempts < $this->maxUploadAttempts) {
                    try {
                        $writeSucceeded = $this->fileSystemAwsS3->writeStream($relativeLocalPath, $fp);
                    } /** @noinspection PhpRedundantCatchClauseInspection */
                    catch (S3Exception $e) {
                        $sleep = 2 ** $countUploadAttempts;
                        $countUploadAttempts++;
                        $this->getLogger()->error("Cannot write file to S3. Attempt {$countUploadAttempts}/{$this->maxUploadAttempts}: '{$e->getMessage()}'");
                        $this->getLogger()->debug("Sleeping {$sleep}s ...");
                        sleep($sleep);
                    }
                }
            } catch (InvalidArgumentException $e) {
                $this->failedPaths[] = $relativeLocalPath;
                $this->getLogger()->error("Cannot write file to S3, skipping: '{$e->getMessage()}'");
                return false;
            }
        } else {
            $writeSucceeded = true;
        }
        fclose($fp);

        if ($writeSucceeded) {
            $this->getLogger()->debug("Upload completed");
            $this->countBytesUploaded += filesize($filepathLocalAbsolute);
        } else {
            $this->failedPaths[] = $relativeLocalPath;
            $this->getLogger()->warning("Upload failed");
            return false;
        }

        if ($this->deleteLocalCopy) {
            $this->getLogger()->debug("Deleting local file '$filepathLocalAbsolute'");
            if ($this->perform()) {
                unlink($filepathLocalAbsolute);
            }
        }

        return true;
    }


    /**
     * Convert a local absolute path to a path relative to the storage dir
     *
     * eg '/var/lib/schoolbox/vagrantx/storage/h/a/s/h/hash' => 'h/a/s/h/hash'
     *
     * @param string $filePathLocalAbsolute
     * @return string
     */
    private function getRelativeLocalPath(string $filePathLocalAbsolute): string
    {
        return str_replace("{$this->storageDir}/", '', $filePathLocalAbsolute);
    }


    /**
     * Find and upload any adjacent transcode files
     *
     * @param string $filepath
     */
    private function moveTranscodeFilesToS3(string $filepath): void
    {
        $fileName = basename($filepath);
        $dirName  = dirname($filepath);
        if (!is_dir($dirName)) {
            // If file already in S3, then directory may not exist locally any more.
            return;
        }
        $handle = opendir($dirName);
        while (false !== ($entry = readdir($handle))) {
            if ($entry === "." || $entry === "..") {
                // Ignore these
                continue;
            }
            if (strpos($entry, "{$fileName}-") !== 0) {
                // Not a transcode of the current file, ignore
                continue;
            }

            $transcodeFilePath = $dirName . DIRECTORY_SEPARATOR . $entry;
            $filepath          = str_replace($this->storageDir . DIRECTORY_SEPARATOR, '', $transcodeFilePath);
            $transcodeFile     = new Flysystem\File($this->fileSystemAwsS3, $filepath);

            $foundInS3 = array_key_exists(FilesystemFactory::PREFIX_STORAGE . $transcodeFile->getPath(), $this->filesInS3);
            if ($foundInS3) {
                $this->getLogger()->info("Transcode file '$entry' found locally and in S3" . ($this->deleteLocalCopy ? ', deleting local copy' : ''));

                if ($this->deleteLocalCopy && $this->perform()) {
                    unlink($transcodeFilePath);
                }
                continue;
            }
            $this->getLogger()->info("Transcode file '$entry' found locally but not in S3, uploading...");
            if ($this->moveFileToS3($transcodeFilePath)) {
                $this->countTranscodeFilesUploaded++;
            } else {
                $this->countUploadsFailed++;
            }
        }
        closedir($handle);
    }


    /**
     * Deletes empty ancestor directories of the specified file
     *
     * @param string $filepath
     */
    private function deleteEmptyDirectoryAncestors(string $filepath): void
    {
        if (!$this->perform()) {
            return;
        }

        // Recursively delete empty folders we might have created since deleting the file
        $levels = 1;
        while (true) {
            $dirname = dirname($filepath, $levels);
            if (!is_dir($dirname)) {
                break;
            }
            if (!$this->isDirectoryEmpty($dirname)) {
                break;
            }
            $this->getLogger()->debug("Removing directory '$dirname'");
            rmdir($dirname);
            if ($dirname === $this->storageDir) {
                // We can remove the storage directory, but no higher, as it's possible to configure Schoolbox
                // to use the storage dir's parent as a configured directory, and it may just happen to be empty.
                break;
            }
            $levels++;
        }
    }


    /**
     * Returns whether a directory is empty
     *
     * @param string $dir
     * @return bool
     */
    private function isDirectoryEmpty(string $dir): bool
    {
        $handle = opendir($dir);
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
     * Logs a list of relative paths where migration failed.
     */
    private function listFailedMigrationPaths(): void
    {
        if (!empty($this->failedPaths)) {
            $this->getLogger()->error("List of paths that failed during migration:\n" . implode("\n", $this->failedPaths));
        }
    }

    /**
     * Print a help message of available options
     */
    private function printHelp(): void
    {
        echo "
Options:
    -h, --help
        Print help message and exit.
    -d, --delete
        If present, delete files from local storage
    -q, --quiet
        Don't derive or print file system usage (faster tool execution)
    -p, --perform
        Without this flag, tool will not persist files to S3 or delete from local storage
";
    }
}
