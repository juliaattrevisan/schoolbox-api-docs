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
use Schoolbox\Application\CliTool;
use Schoolbox\MapperFactory;
use Schoolbox\Resource\Storage;
use function array_filter;
use function basename;
use function chunk_split;
use function count;
use function explode;
use function fclose;
use function file_exists;
use function filesize;
use function fopen;
use function get_class;
use function is_resource;
use function rmdir;
use function sprintf;
use function str_replace;
use function unlink;

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


    public function getShortOpts(): array
    {
        return ['h'];
    }


    public function getLongOpts(): array
    {
        return ['help'];
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
        $count = $countUploaded = 0;

        // Retrieve and iterate all files from the database
        $files    = $this->storageMapper->findAll();
        $countAll = $files->count();
        foreach ($files as $file) {
            $count++;
            $this->getLogger()->notice("Checking file $count/$countAll  {$file->getHash()}, '{$file->getBasename()}'");
            if ($this->migrateFile($file)) {
                $countUploaded++;
            }
        }

        $this->getLogger()->notice(sprintf("Files in S3:     %d", $countUploaded));
        $this->getLogger()->notice(sprintf("Files not in S3: %d", $countAll - $countUploaded));
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

        if (!$foundLocally) {
            if (!$foundInS3) {
                $this->getLogger()->warning("File not found locally or in S3, skipping");
                return false;
            }
            $this->getLogger()->info("File not found locally but exists in S3, skipping");
            return true;
        }


        // File was found locally, but not in S3
        if (!$foundInS3 && !$this->moveFileToS3($filepath, $file)) {
            $this->getLogger()->info("File on local disk but not in S3, uploading... (" . $this->byteFormat::bytesToString(filesize($filepath)) . ")");
            return false;
        }

        if ($foundLocally && $this->perform()) {
            $this->getLogger()->info("File in S3, deleting local file '$filepath'");
            unlink($filepath);
            // Recursively delete empty folders we might have created since deleting the file
            $directoryArray = array_filter(explode("/", chunk_split(basename($filepath), 5, '/')));
            $filepath       = str_replace("/" . basename($filepath), "", $filepath);
            for ($i = count($directoryArray) - 1; $i >= 0; $i--) {
                if (!$this->isDirectoryEmpty($filepath)) {
                    break;
                }
                $this->getLogger()->debug("Removing directory '$filepath'");
                rmdir($filepath);
                $filepath = str_replace("/" . $directoryArray[$i], "", $filepath);
            }
        }

        return true;
    }


    /**
     * Moves file from local to S3 filesystem
     *
     * @param string        $filepath
     * @param Storage\Model $file
     * @return bool Whether the move was successful
     */
    private function moveFileToS3(string $filepath, Storage\Model $file): bool
    {
        $fp = fopen($filepath, 'rb');
        if (!is_resource($fp)) {
            $this->getLogger()->error("Cannot open file for reading, skipping");
            return false;
        }

        if ($this->perform()) {
            $file->writeStream($fp);
        }
        fclose($fp);
        $this->getLogger()->debug("Upload Completed");
        return true;
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
    -p, --perform
        Without this flag, tool will not persist files to S3 or delete from local storage
";
    }
}
