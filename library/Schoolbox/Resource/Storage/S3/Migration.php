<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Alaress_Byte_Format;
use Aura\Di\Exception\ServiceNotFound;
use InvalidArgumentException;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem;
use Monolog\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage;
use function array_filter;
use function array_key_exists;
use function basename;
use function chunk_split;
use function closedir;
use function count;
use function dirname;
use function explode;
use function fclose;
use function file_exists;
use function filesize;
use function fopen;
use function get_class;
use function is_dir;
use function is_resource;
use function opendir;
use function readdir;
use function rmdir;
use function shell_exec;
use function sprintf;
use function str_replace;
use function strpos;
use function unlink;
use const DIRECTORY_SEPARATOR;

/**
 * Migrates all files from local configured 'storage_dir' to AWS S3
 */
class Migration extends CliTool\App
{

    /** @var Storage\Mapper */
    private $storageMapper;

    /**
     * Configured local storage directory
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

    /**
     * Should we delete files from local storage?
     * @var bool
     */
    private $deleteLocalCopy;

    /**
     * Indexed by filepath
     * @var bool[]
     */
    private $filesTransferred;


    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['h', 'd'];
    }


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['help', 'delete'];
    }


    /**
     * @inheritDoc
     * @throws ServiceNotFound
     * @throws InvalidArgumentException
     */
    public function run(): void
    {
        $this->getLogger()->info('S3 Storage Migration');

        if ($this->getOpt('h') || $this->getOpt('help')) {
            $this->printHelp();
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
        $this->deleteLocalCopy = $this->getOpt('d') || $this->getOpt('delete');
        $level                 = $this->deleteLocalCopy ? Logger::WARNING : Logger::NOTICE;
        $this->getLogger()->log($level, ($this->deleteLocalCopy ? 'Deleting' : 'Keeping') . ' local storage files');

        /** @var MapperFactory $mapperFactory */
        $mapperFactory       = $this->getDi()->get('mapperFactory');
        $this->storageMapper = $mapperFactory->get(Storage\Mapper::class);
        $this->storageDir    = $this->getConfig()->get('storage_dir');
        $this->byteFormat    = new Alaress_Byte_Format();

        $this->checkUsingS3FileSystem();
        $this->migrateFiles();
    }


    /**
     * Ensure the system is configure to use AWS S3 as the 'storage' file system
     */
    private function checkUsingS3FileSystem(): void
    {
        /** @var Flysystem\Filesystem $storageFileSystem */
        $storageFileSystem = $this->storageMapper->getManager()->getFilesystem(Storage\Manager::FS_STORAGE);
        if (!$storageFileSystem->getAdapter() instanceof AwsS3Adapter) {
            $this->getLogger()->error("Error: Unexpected file adapter - " . get_class($storageFileSystem->getAdapter()));
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }
    }


    /**
     * Retrieves and migrates all local storage files to S3
     */
    private function migrateFiles(): void
    {
        // Reset counts for logging
        $countFilesInS3        = $count = $this->countStorageFilesUploaded = $this->countTranscodeFilesUploaded = $this->countBytesUploaded = 0;
        $storageBeforeTransfer = $this->getDirectorySize($this->storageDir);
        $this->filesTransferred = [];

        // Retrieve and iterate all files referenced by `storage` table in database
        $files    = $this->storageMapper->findAll();
        $countAll = $files->count();
        foreach ($files as $file) {
            $count++;
            $this->getLogger()->notice("Checking file $count/$countAll  {$file->getHash()}, '{$file->getBasename()}'");
            if ($this->migrateFile($file)) {
                $countFilesInS3++;
            }
        }

        if (!$this->deleteLocalCopy || $this->perform()) {
            $storageAfterTransfer = $this->getDirectorySize($this->storageDir);
        } else {
            $storageAfterTransfer = $storageBeforeTransfer - $this->countBytesUploaded;
        }
        $this->getLogger()->notice(sprintf("Files in storage table:     %d", $countAll));
        $this->getLogger()->notice(sprintf("Storage files uploaded:     %d", $this->countStorageFilesUploaded));
        $this->getLogger()->notice(sprintf("Transcode files uploaded:   %d", $this->countTranscodeFilesUploaded));
        $this->getLogger()->notice(sprintf("Data transferred:           %s", $this->byteFormat::bytesToString($this->countBytesUploaded)));
        $this->getLogger()->notice(sprintf("Storage before transfer:    %s", $this->byteFormat::bytesToString($storageBeforeTransfer)));
        $this->getLogger()->notice(sprintf("Storage after transfer:     %s", $this->byteFormat::bytesToString($storageAfterTransfer)));
        $this->getLogger()->notice(sprintf("Storage files in S3:        %d", $countFilesInS3));
        $countNotInS3 = $countAll - $countFilesInS3;
        $level        = $countNotInS3 ? Logger::WARNING : Logger::NOTICE;
        $this->getLogger()->log($level, sprintf("Storage files not in S3:    %d", $countNotInS3));

        $remainingFiles = $this->getRemainingFiles();
        $level          = count($remainingFiles) ? Logger::WARNING : Logger::NOTICE;
        $this->getLogger()->log($level, sprintf("Files in local storage but not in S3: %d", count($remainingFiles)));
        foreach ($remainingFiles as $remainingFile) {
            $this->getLogger()->debug($remainingFile);
        }
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
        $filesInLocalStorage = explode(PHP_EOL, shell_exec("find {$this->storageDir} -type f") ?? '');
        foreach ($filesInLocalStorage as $fileInLocalStorage) {
            if (array_key_exists($fileInLocalStorage, $this->filesTransferred) || $fileInLocalStorage === '') {
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
        return (int)shell_exec("du -s -B 1 {$dirName} | cut -f 1");
    }


    /**
     * Migrate a file from local storage to S3
     *
     * @param Storage\Model $file
     * @return bool Whether the file is now in S3 (either previously or newly uploaded)
     */
    private function migrateFile(Storage\Model $file): bool
    {
        // Does file exist locally?
        $filepath     = "{$this->storageDir}/{$file->getPath()}";
        $foundLocally = file_exists($filepath);

        // Does file already exist in S3?
        $foundInS3 = $file->exists();

        // Handle each of four possible scenarios re: file's presence on local and S3 filesystems:
        if (!$foundLocally) {
            if (!$foundInS3) {
                $this->getLogger()->warning("File not found locally or in S3, skipping");
            } else {
                $this->getLogger()->info("File not found locally but exists in S3, skipping");
            }
        } elseif (!$foundInS3) {
            $this->getLogger()->info("File on local disk but not in S3, uploading... (" . $this->byteFormat::bytesToString(filesize($filepath)) . ")");
            if ($this->moveFileToS3($filepath, $file)) {
                $foundInS3 = true;
                $this->countStorageFilesUploaded++;
            }
        } else {
            $this->getLogger()->info("File both on local disk and in S3, deleting local copy");
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
     * @param string         $filepath
     * @param Flysystem\File $file
     * @return bool Whether the move was successful
     */
    private function moveFileToS3(string $filepath, Flysystem\File $file): bool
    {
        $fp = fopen($filepath, 'rb');
        if (!is_resource($fp)) {
            $this->getLogger()->error("Cannot open file for reading, skipping");
            return false;
        }

        if ($this->perform()) {
            $writeSucceeded = $file->writeStream($fp);
        } else {
            $writeSucceeded = true;
        }
        fclose($fp);

        if ($writeSucceeded) {
            $this->getLogger()->debug("Upload completed");
            $this->countBytesUploaded += filesize($filepath);
            $this->filesTransferred[$filepath] = true;
        } else {
            $this->getLogger()->warning("Upload failed");
            return false;
        }

        $this->getLogger()->debug("Deleting local file '$filepath'");
        if ($this->deleteLocalCopy && $this->perform()) {
            unlink($filepath);
        }

        return true;
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
            $this->getLogger()->info("Transcode file '$entry' found");
            $transcodeFilePath = $dirName . DIRECTORY_SEPARATOR . $entry;
            $filepath          = str_replace($this->storageDir . DIRECTORY_SEPARATOR, '', $transcodeFilePath);
            $transcodeFile     = new Flysystem\File($this->storageMapper->getManager()->getFilesystem(Storage\Manager::FS_STORAGE), $filepath);
            if ($transcodeFile->exists()) {
                $this->getLogger()->debug("Transcode file already in S3, deleting local copy");
                if ($this->perform()) {
                    unlink($transcodeFilePath);
                }
                continue;
            }
            $this->getLogger()->debug("Transcode file not in S3, uploading...");
            if ($this->moveFileToS3($transcodeFilePath, $transcodeFile)) {
                $this->countTranscodeFilesUploaded++;
            }
        }
        closedir($handle);
    }


    /**
     * Deletes empty ancestor directories between the specified file and root directory
     *
     * @param string $filepath
     */
    private function deleteEmptyDirectoryAncestors(string $filepath): void
    {
        if (!$this->perform()) {
            return;
        }

        // Recursively delete empty folders we might have created since deleting the file
        $directoryArray = array_filter(explode("/", chunk_split(basename($filepath), 5, '/')));
        $dir            = str_replace("/" . basename($filepath), "", $filepath);
        for ($i = count($directoryArray) - 1; $i >= 0; $i--) {
            if (!is_dir($dir)) {
                continue;
            }
            if (!$this->isDirectoryEmpty($dir)) {
                break;
            }
            $this->getLogger()->debug("Removing directory '$dir'");
            rmdir($dir);
            $dir = str_replace("/" . $directoryArray[$i], "", $dir);
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
    -p, --perform
        Without this flag, tool will not persist files to S3 or delete from local storage
";
    }
}
