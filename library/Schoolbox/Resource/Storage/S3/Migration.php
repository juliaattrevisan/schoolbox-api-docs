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
use Aws\S3\S3Client;
use InvalidArgumentException;
use League\Flysystem\AwsS3v3\AwsS3Adapter;
use League\Flysystem;
use League\Flysystem\Filesystem;
use Monolog\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\Resource\Storage;

use function array_filter;
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
use function realpath;
use function rmdir;
use function shell_exec;
use function sleep;
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

        $this->fileSystemAwsS3 = $this->buildAwsS3FileSystemAndFetchS3Files();

        $this->migrateFiles();
    }

    /**
     * Fetch the keys of all files found in the bucket in an array where the key is the element index
     *
     * @param S3Client $s3Client
     * @param string   $bucketName
     */
    private function getFilesInS3(S3Client $s3Client, string $bucketName): void
    {
        $this->filesInS3  = [];
        $this->getLogger()->notice("Fetching file list from S3, this may take a while on large datasets...");
        foreach ($s3Client->getIterator('ListObjects', ['Bucket' => $bucketName, 'Prefix' => FilesystemFactory::PREFIX_STORAGE]) as $i => $object) {
            if($i && $i % 1000 === 0){
                $this->getLogger()->debug("Files found in S3: $i...");
             }
            $this->filesInS3[$object['Key']] = true;
        }
    }

    /**
     * Build and return an AWS S3 file system, regardless of whether the system is configured to use Local or AWS S3.
     *
     * @return Filesystem
     * @throws InvalidArgumentException
     */
    private function buildAwsS3FileSystemAndFetchS3Files(): Filesystem
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

        $this->getFilesInS3($client, $s3Bucket);

        return new Filesystem(new AwsS3Adapter($client, $s3Bucket, FilesystemFactory::PREFIX_STORAGE));
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
            $baseName = "{$file['filename']}.{$file['extension']}";
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
     * @param string         $filepathLocalAbsolute
     * @return bool Whether the move was successful
     * @throws Flysystem\FileExistsException
     */
    private function moveFileToS3(string $filepathLocalAbsolute): bool
    {
        $fp = fopen($filepathLocalAbsolute, 'rb');
        if (!is_resource($fp)) {
            $this->getLogger()->error("Cannot open file for reading, skipping");
            return false;
        }

        $relativeLocalPath = $this->getRelativeLocalPath($filepathLocalAbsolute);
        if ($this->perform()) {
            $writeSucceeded= false;
            try {
                $countUploadAttempts = 0;
                while (!$writeSucceeded && $countUploadAttempts < $this->maxUploadAttempts) {
                    try{
                        $writeSucceeded = $this->fileSystemAwsS3->writeStream($relativeLocalPath, $fp);
                    } /** @noinspection PhpRedundantCatchClauseInspection */
                    catch (S3Exception $e){
                        $sleep = 2 ** $countUploadAttempts;
                        $countUploadAttempts++;
                        $this->getLogger()->error("Cannot write file to S3. Attempt {$countUploadAttempts}/{$this->maxUploadAttempts}: '{$e->getMessage()}'");
                        $this->getLogger()->debug("Sleeping {$sleep}s ...");
                        sleep($sleep);
                    }
                }
            } catch (InvalidArgumentException $e) {
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
