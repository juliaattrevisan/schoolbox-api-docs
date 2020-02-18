<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Alaress_Byte_Format;
use Aura\Di\Exception\ServiceNotFound;
use Aws\S3\S3Client;
use InvalidArgumentException;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem;
use League\Flysystem\Filesystem;
use LogicException;
use Monolog\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage;
use function array_key_exists;
use function basename;
use function closedir;
use function count;
use function dirname;
use function explode;
use function fclose;
use function file_exists;
use function filesize;
use function fopen;
use function is_dir;
use function is_resource;
use function opendir;
use function readdir;
use function rmdir;
use function shell_exec;
use function sprintf;
use function str_pad;
use function str_replace;
use function strlen;
use function strpos;
use function trim;
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

    /** @var Filesystem */
    private $fileSystemAwsS3;

    /**
     * @inheritDoc
     * @throws ServiceNotFound
     * @throws InvalidArgumentException
     * @throws LogicException
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
        $this->storageDir    = rtrim($this->getConfig()->get('storage_dir'), DIRECTORY_SEPARATOR);
        $this->byteFormat    = new Alaress_Byte_Format();

        $this->fileSystemAwsS3 = $this->buildAwsS3FileSystem();

        $this->migrateFiles();
    }

    /**
     * Build and return an AWS S3 file system, regardless of whether the system is configured to use Local or AWS S3.
     *
     * @return Filesystem
     * @throws InvalidArgumentException
     */
    private function buildAwsS3FileSystem(): Filesystem
    {
        $s3AccessKey = trim($this->getConfig()->get('otfs_s3_cred_key') ?? "");
        $s3SecretKey = trim($this->getConfig()->get('otfs_s3_cred_secret') ?? "");
        $s3Region    = trim($this->getConfig()->get('otfs_s3_region') ?? "");
        $s3Version   = trim($this->getConfig()->get('otfs_s3_version') ?? "");
        $s3Bucket    = trim($this->getConfig()->get('otfs_s3_bucket') ?? "");

        $this->getLogger()->debug(sprintf("OTFS Adapter: '%s'", $this->getConfig()->get(ConfigMapper::NAME_OTFS_ADAPTER)));
        $this->getLogger()->debug("S3 Access Key: '$s3AccessKey'");
        $this->getLogger()->debug(sprintf("S3 Secret Key: '%s'", str_pad("", strlen($s3SecretKey), "*")));
        $this->getLogger()->debug("S3 Region: '$s3Region'");
        $this->getLogger()->debug("S3 Version: '$s3Version'");
        $this->getLogger()->debug("S3 Bucket: '$s3Bucket'");

        if (empty($s3AccessKey) || empty($s3SecretKey) || empty($s3Region) || empty($s3Version) || empty($s3Bucket)) {
            $this->getLogger()->error("Error: S3 config appears invalid");
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }

        $client = new S3Client([
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
        ]);

        return new Filesystem(new AwsS3Adapter($client, $s3Bucket));
    }


    /**
     * Retrieves and migrates all local storage files to S3
     */
    private function migrateFiles(): void
    {
        // Reset counts for logging
        $countFilesInS3        = $count = $this->countStorageFilesUploaded = $this->countTranscodeFilesUploaded = $this->countBytesUploaded = 0;
        $storageBeforeTransfer = $this->getDirectorySize($this->storageDir);
        $this->filesInS3       = [];

        // Retrieve and iterate all files referenced by `storage` table in database
        $files    = $this->storageMapper->findAll();
        $countAll = $files->count();
        foreach ($files as $file) {
            // Set file to use AWS S3 filesystem, regardless of Schoolbox's currently configured file system
            $file->setFilesystem($this->fileSystemAwsS3);
            $count++;
            $this->getLogger()->notice("Checking file $count/$countAll  {$file->getHash()}, '{$file->getBasename()}'");
            if ($this->migrateFile($file)) {
                $countFilesInS3++;
            }
        }

        $remainingFiles = $this->getRemainingFiles();
        if ($this->deleteLocalCopy && !$this->perform()) {
            $storageAfterTransfer = 0;
            // Directories also take up storage space
            $directories = [];
            foreach ($remainingFiles as $remainingFile) {
                $storageAfterTransfer += filesize($remainingFile);
                $directories          = $this->addFileDirectoryAncestors($directories, $remainingFile);
            }
            foreach ($directories as $directory => $_) {
                $storageAfterTransfer += shell_exec("stat -c%s $directory");
            }
        } else {
            $storageAfterTransfer = $this->getDirectorySize($this->storageDir);
        }
        $this->getLogger()->notice(sprintf("Files in storage table:     %d", $countAll));
        $this->getLogger()->notice(sprintf("Storage files uploaded:     %d", $this->countStorageFilesUploaded));
        $this->getLogger()->notice(sprintf("Transcode files uploaded:   %d", $this->countTranscodeFilesUploaded));
        $this->getLogger()->notice(sprintf("Data transferred:           %s", $this->byteFormat::bytesToString($this->countBytesUploaded)));
        $this->getLogger()->notice(sprintf("Storage before transfer:    %s", $this->byteFormat::bytesToString($storageBeforeTransfer)));
        $this->getLogger()->notice(sprintf("Storage after transfer:     %s", $this->byteFormat::bytesToString($storageAfterTransfer)));
        $this->getLogger()->notice(sprintf("Storage files in S3:        %d", $countFilesInS3));
        $countNotInS3 = $countAll - $countFilesInS3;
        $this->getLogger()->log($countNotInS3 ? Logger::WARNING : Logger::NOTICE, sprintf("Storage files not in S3:    %d", $countNotInS3));
        $this->getLogger()->log(count($remainingFiles) ? Logger::WARNING : Logger::NOTICE, sprintf("Files in local storage but not in S3: %d", count($remainingFiles)));
        foreach ($remainingFiles as $remainingFile) {
            $this->getLogger()->debug($remainingFile);
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
        $filesInLocalStorage = explode(PHP_EOL, shell_exec("find {$this->storageDir} -type f") ?? '');
        foreach ($filesInLocalStorage as $fileInLocalStorage) {
            if (array_key_exists($fileInLocalStorage, $this->filesInS3) || $fileInLocalStorage === '') {
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
        return (int)shell_exec("du -sb {$dirName} | cut -f 1");
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
            $this->getLogger()->info('File both on local disk and in S3' . ($this->deleteLocalCopy ? ', deleting local copy' : ''));
            if ($this->deleteLocalCopy && $this->perform()) {
                unlink($filepath);
            }
        }

        if ($foundInS3) {
            $this->filesInS3[$filepath] = true;
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
        } else {
            $this->getLogger()->warning("Upload failed");
            return false;
        }

        if ($this->deleteLocalCopy) {
            $this->getLogger()->debug("Deleting local file '$filepath'");
            if ($this->perform()) {
                unlink($filepath);
            }
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

            $transcodeFilePath = $dirName . DIRECTORY_SEPARATOR . $entry;
            $filepath          = str_replace($this->storageDir . DIRECTORY_SEPARATOR, '', $transcodeFilePath);
            $transcodeFile     = new Flysystem\File($this->fileSystemAwsS3, $filepath);

            if ($transcodeFile->exists()) {
                $this->filesInS3[$transcodeFilePath] = true;
                $this->getLogger()->info("Transcode file '$entry' found locally and in S3" . ($this->deleteLocalCopy ? ', deleting local copy' : ''));

                if ($this->deleteLocalCopy && $this->perform()) {
                    unlink($transcodeFilePath);
                }
                continue;
            }
            $this->getLogger()->info("Transcode file '$entry' found locally but not in S3, uploading...");
            if ($this->moveFileToS3($transcodeFilePath, $transcodeFile)) {
                $this->filesInS3[$transcodeFilePath] = true;
                $this->countTranscodeFilesUploaded++;
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
