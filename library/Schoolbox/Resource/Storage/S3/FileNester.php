<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Alaress\Database\Exception as DatabaseException;
use Aws\S3\S3Client;
use InvalidArgumentException;
use Monolog\Logger;
use PDO;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\FilesystemFactory;

use function array_combine;
use function array_key_exists;
use function count;
use function in_array;
use function number_format;
use function preg_match;
use function sprintf;
use function str_pad;
use function strlen;
use function strpos;
use function strtolower;
use function time;
use function trim;

/**
 * Moves all storage files in S3 below a top-level folder called 'storage'
 *
 * This tools has three modes:
 * 1. COPY files from '\x' to '\storage\x' (ie original is RETAINED)
 * 2. MOVE files from '\x' to '\storage\x' (ie original is DELETED)
 *
 * We need to iterate the whole structure (because of transforms), and just ignore files we
 * recognise as not being 'storage' files
 *
 * The overhead of a single call to S3 is large. So we therefore want to fetch as much data as possible per call
 * and process this in local memory.
 */
class FileNester extends CliTool\App
{
    private const OPT_LONG_ACTION = 'action:';

    private const ACTION_MOVE = 'move';
    private const ACTION_COPY = 'copy';


    /**
     * Is tool running in 'move' or 'copy' mode?
     *
     * @var bool
     */
    private $actionMode;

    /** @var S3Client */
    private $s3Client;

    /**
     * All files in S3, indexed by key
     *
     * We maintain this as a class property, rather than method param, as it is slow to pass a large array as a param
     *
     * @var string[]
     */
    private $filesInS3;

    /**
     * All hashes in the storage table, indexed by hash
     *
     * We maintain this as a class property, rather than method param, as it is slow to pass a large array as a param
     *
     * @var string[]
     */
    private $hashesInStorageTable;

    /** @var string */
    private $bucketName;

    /**
     * S3 files that appear to be irrelevant backup/DR files
     * @var int
     */
    private $countBackupFiles = 0;

    /**
     * S3 files that appear to already be suitably nested
     * @var int
     */
    private $countNestedFiles = 0;

    /**
     * S3 storage files that appear to require migration under 'storage' dir
     * @var int
     */
    private $countUnNestedStorageFiles = 0;

    /**
     * S3 transform files that appear to require migration under 'storage' dir
     * @var int
     */
    private $countUnNestedTransformFiles = 0;


    /**
     * S3 files that are not recognised by tool
     * @var string[]
     */
    private $unRecognisedFiles = [];

    /** @var int */
    private $countFilesMoved = 0;
    /** @var int */
    private $countFilesCopied = 0;
    /** @var int */
    private $countFilesDeleted = 0;

    /** @var int */
    private $startTime;


    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['h'];
    }


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['help', self::OPT_LONG_ACTION];
    }


    /**
     * @inheritDoc
     * @throws InvalidArgumentException
     * @throws DatabaseException
     */
    public function run(): void
    {
        $this->startTime = time();
        $this->getLogger()->info(sprintf("S3 File Copy/Move to top-level directory '/%s'", FilesystemFactory::PREFIX_STORAGE));
        $this->parseArgs();
        $this->buildDependencies();
        $this->processFiles();
        $this->printResults();
    }


    /**
     * Parse CLI args
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    private function parseArgs(): void
    {
        if ($this->getOpt('h') || $this->getOpt('help')) {
            $this->printHelp();
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
        $this->actionMode = strtolower($this->getOpt(self::OPT_LONG_ACTION) ?? '');
        if (!in_array($this->actionMode, [self::ACTION_MOVE, self::ACTION_COPY], true)) {
            $this->getLogger()->error(sprintf(
                "Invalid action mode. Must be one of ['%s','%s'], found: '%s'",
                self::ACTION_MOVE,
                self::ACTION_COPY,
                $this->getOpt(self::OPT_LONG_ACTION)
            ));
            $this->printHelp();
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
        $this->getLogger()->log(
            $this->actionMode === self::ACTION_MOVE ? Logger::WARNING : Logger::NOTICE,
            "Action mode: '{$this->actionMode}' - original file will be " .
            ($this->actionMode === self::ACTION_MOVE ? 'DELETED' : 'RETAINED')
        );
    }


    /**
     * Build all services required by this class
     *
     * @throws InvalidArgumentException
     */
    private function buildDependencies(): void
    {
        $s3AccessKey = trim($this->getConfig()->get('otfs_s3_cred_key') ?? "");
        $s3SecretKey = trim($this->getConfig()->get('otfs_s3_cred_secret') ?? "");
        $s3Region    = trim($this->getConfig()->get('otfs_s3_region') ?? "");
        $s3Version   = trim($this->getConfig()->get('otfs_s3_version') ?? "");
        $this->bucketName = trim($this->getConfig()->get('otfs_s3_bucket') ?? "");

        $otfsAdapter = $this->getConfig()->get(ConfigMapper::NAME_OTFS_ADAPTER);
        $this->getLogger()->debug(sprintf("OTFS Adapter: '%s'", $otfsAdapter));
        $this->getLogger()->debug("S3 Access Key: '$s3AccessKey'");
        $this->getLogger()->debug(sprintf("S3 Secret Key: '%s'", str_pad("", strlen($s3SecretKey), "*")));
        $this->getLogger()->debug("S3 Region: '$s3Region'");
        $this->getLogger()->debug("S3 Version: '$s3Version'");
        $this->getLogger()->debug("S3 Bucket: '$this->bucketName'");

        $usingS3   = $otfsAdapter === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3;
        $badConfig = empty($s3AccessKey) || empty($s3SecretKey) || empty($s3Region) || empty($s3Version) || empty($this->bucketName);

        // Using S3 but invalid config. Error out.
        if ($usingS3 && $badConfig) {
            $this->getLogger()->error("S3 config appears invalid");
            exit(CliTool\Kernel::EXIT_STATUS_APP_ERROR);
        }
        // Using S3 and good config, continue
        if ($usingS3 && !$badConfig) {
            $this->getLogger()->notice("S3 config appears valid");
        }
        // Not using S3, invalid config. Tool probably not relevant, just exit.
        if (!$usingS3 && $badConfig) {
            $this->getLogger()->notice("S3 config appears invalid, but not using S3, exiting");
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
        // Not using S3, but S3 config present. Assume files exists in both places (eg Vagrant case)
        if (!$usingS3 && !$badConfig) {
            $this->getLogger()->warning("S3 config appears valid, but not using S3, connection error probably ok to ignore");
        }

        $this->s3Client = new S3Client([
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
        ]);
    }


    /**
     * Retrieves and migrates all local storage files to S3
     * @throws DatabaseException
     */
    private function processFiles(): void
    {
        $this->hashesInStorageTable = $this->getFilesInStorageTable();
        $this->filesInS3            = $this->getFilesInS3();

        $total = count($this->filesInS3);
        $i = 1;
        foreach ($this->filesInS3 as $fileInS3) {
            $this->getLogger()->notice("Processing S3 file ($i/$total): '$fileInS3'");
            $this->processS3File($fileInS3);
            $i++;
        }
    }


    /**
     * Returns all hashes in storage table
     *
     * @return string[]
     * @throws DatabaseException
     */
    private function getFilesInStorageTable(): array
    {
        $hashes = $this->getDatabaseInternal()->executeQuery("SELECT hash FROM storage")->fetchAll(PDO::FETCH_COLUMN);
        return array_combine($hashes, $hashes);
    }


    /**
     * Returns they keys of all files found in the bucket
     *
     * @return string[]
     */
    private function getFilesInS3(): array
    {
        $iterator  = $this->s3Client->getIterator('ListObjects', ['Bucket' => $this->bucketName]);
        $filesInS3 = [];
        foreach ($iterator as $i => $object) {
            $filesInS3[] = $object['Key'];
        }
        return array_combine($filesInS3, $filesInS3);
    }


    /**
     * Process a file found in S3
     *
     * @param string $fileInS3   eg
     *                           'fe6bf/346d2/c72e2/8d641/55b7e/b1f92/0a4d9/6095d/fe6bf346d2c72e28d64155b7eb1f920a4d96095d'
     *                           'fe6bf/346d2/c72e2/8d641/55b7e/b1f92/0a4d9/6095d/fe6bf346d2c72e28d64155b7eb1f920a4d96095d-maxsize'
     *                           'storage/fe6bf/346d2/c72e2/8d641/55b7e/b1f92/0a4d9/6095d/fe6bf346d2c72e28d64155b7eb1f920a4d96095d'
     *                           'storage/fe6bf/346d2/c72e2/8d641/55b7e/b1f92/0a4d9/6095d/fe6bf346d2c72e28d64155b7eb1f920a4d96095d-maxsize'
     *                           'backups/some/unrelated/file.txt'
     */
    private function processS3File(string $fileInS3): void
    {
        if (strpos($fileInS3, DisasterRecovery::PREFIX_BACKUPS) === 0) {
            // Unrelated backup/DR file?
            $this->getLogger()->info("File is a 'backup/DR' file, ignoring");
            $this->countBackupFiles++;
        } elseif (strpos($fileInS3, FilesystemFactory::PREFIX_STORAGE) === 0) {
            // Already nested under /storage top-level dir?
            $this->getLogger()->info("File appears to be a already be nested, ignoring");
            $this->countNestedFiles++;
        } elseif (
            preg_match(
                "#^[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/([0-9a-f]{40})$#",
                $fileInS3,
                $matches
            )
        ) {
            // Un-nested storage file?
            $this->getLogger()->info("File is a un-nested storage file");
            $this->countUnNestedStorageFiles++;
            $this->processS3StorageFile($fileInS3, $matches[1], false);
        } elseif (
            preg_match(
                "#^[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/[0-9a-f]{5}/([0-9a-f]{40})-[\w-]+$#",
                $fileInS3,
                $matches
            )
        ) {
            // Un-nested transform file?
            $this->getLogger()->info("File is a un-nested transform file");
            $this->countUnNestedTransformFiles++;
            $this->processS3StorageFile($fileInS3, $matches[1], true);
        } else {
            // Unrecognised?
            $this->getLogger()->warning("Unrecognised file in S3 bucket: '$fileInS3'");
            $this->unRecognisedFiles[] = $fileInS3;
        }
    }


    /**
     * Process a S3 file that appears to be an un-nested storage or transform file
     *
     * @param string $fileInS3
     * @param string $hash
     * @param bool   $isTransform
     */
    private function processS3StorageFile(string $fileInS3, string $hash, bool $isTransform): void
    {
        // TODO when IST-236 is merged, check for presence in new transform table
        if (!$isTransform) {
            if (array_key_exists($hash, $this->hashesInStorageTable)) {
                $this->getLogger()->debug("Hash found in storage table");
            } else {
                $this->getLogger()->warning("Hash not found in storage table");
            }
        }

        $migrated = FilesystemFactory::PREFIX_STORAGE . $fileInS3;
        if (array_key_exists($migrated, $this->filesInS3)) {
            $this->getLogger()->info("Corresponding nested file found");
            if ($this->actionMode === self::ACTION_MOVE) {
                $this->deleteS3File($fileInS3);
                $this->countFilesDeleted++;
            }
        } else {
            $this->getLogger()->info("Corresponding nested file not found, {$this->actionMode}ing...");
            if ($this->actionMode === self::ACTION_MOVE) {
                $this->copyFile($fileInS3, $migrated);
                $this->deleteS3File($fileInS3);
                $this->countFilesMoved++;
            } else {
                $this->copyFile($fileInS3, $migrated);
                $this->countFilesCopied++;
            }
        }
    }


    /**
     * Ensures a file is deleted from S3
     *
     * @param string $fileInS3
     */
    private function deleteS3File(string $fileInS3): void
    {
        if (!$this->perform()) {
            $this->getLogger()->info("Would have deleted S3 file: $fileInS3");
        } else {
            $result = $this->s3Client->deleteObject([
                'Bucket' => $this->bucketName,
                'Key'    => $fileInS3,
            ]);
            if ($result['DeleteMarker']) {
                $this->getLogger()->info("S3 file deleted or does not exist: $fileInS3");
            } else {
                $this->getLogger()->warning("Unable to delete S3 file: $fileInS3");
            }
        }
    }


    /**
     * Copies an S3 file
     *
     * @param string $fromKey
     * @param string $toKey
     */
    private function copyFile(string $fromKey, string $toKey): void
    {
        if (!$this->perform()) {
            $this->getLogger()->info("Would have copied S3 file '$fromKey' to '$toKey'");
        } else {
            $this->s3Client->copyObject([
                'CopySource' => "{$this->bucketName}/{$fromKey}",
                'Bucket'     => $this->bucketName,
                'Key'        => $toKey,
            ]);
            $this->getLogger()->info("Copied S3 file '$fromKey' to '$toKey'");
        }
    }


    /**
     * Prints the tool's results
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    private function printResults(): void
    {
        $this->getLogger()->notice(sprintf("Run time in seconds:               %s", number_format(time() - $this->startTime)));
        $this->getLogger()->notice(sprintf("Files in S3:                       %d", count($this->filesInS3)));
        $this->getLogger()->notice(sprintf("Backup/DR files:                   %d", $this->countBackupFiles));
        $this->getLogger()->notice(sprintf("Files already nested:              %d", $this->countNestedFiles));
        $this->getLogger()->notice(sprintf("UnNested storage files:            %d", $this->countUnNestedStorageFiles));
        $this->getLogger()->notice(sprintf("UnNested transform files:          %d", $this->countUnNestedTransformFiles));

        $tense         = $this->perform() ? "    were" :  "would be";

        $this->getLogger()->notice(sprintf("Count files that $tense copied:  %d", $this->countFilesCopied));
        $this->getLogger()->log(
            $this->countFilesMoved ? Logger::WARNING : Logger::NOTICE,
            sprintf("Count files that $tense moved:   %d", $this->countFilesMoved)
        );
        $this->getLogger()->log(
            $this->countFilesDeleted ? Logger::WARNING : Logger::NOTICE,
            sprintf("Count files that $tense deleted: %d", $this->countFilesDeleted)
        );

        $this->getLogger()->log(
            count($this->unRecognisedFiles) ? Logger::WARNING : Logger::NOTICE,
            sprintf("Unrecognised files:                %d", count($this->unRecognisedFiles))
        );
        foreach ($this->unRecognisedFiles as $unRecognisedFile) {
            $this->getLogger()->warning("    {$unRecognisedFile}");
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
    --action = ['copy','move']
        Whether to move (original deleted) or copy (original retained) the file
    -p, --perform
        Without this flag, tool will only display proposed actions, without actually performing them
";
    }
}
