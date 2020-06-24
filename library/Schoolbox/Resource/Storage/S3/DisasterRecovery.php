<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

use Alaress_Date_Format;
use InvalidArgumentException;
use League\Flysystem;
use League\Flysystem\Filesystem;
use PDOException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\FilesystemFactory;
use SplFileInfo;
use Throwable;
use UnexpectedValueException;
use function array_merge;
use function dirname;
use function filesize;
use function fopen;
use function implode;
use function is_dir;
use function is_resource;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;

/**
 * * Syncs local files with S3
 * * Backs up DB/Redis/conf to S3
 */
class DisasterRecovery extends CliTool\App
{
    /**
     * Backups are stored in S3 below this directory
     *
     * @var string
     */
    public const PREFIX_BACKUPS = 'backups/';

    public const CONFIG_DR_VISIBILITY = 'aws_s3_dr_backup_visibility';
    public const CONFIG_DR_SYNC_STATUS = 'aws_s3_dr_sync_status';

    // The daily directory we will upload to S3, eg '2020-06-01'
    private const OPT_LONG_BACKUP_DIR = 'backup-dir:';

    /**
     * Errors that occurred during execution but did not cause execution to halt
     *
     * @var string[]
     */
    private $notFatalErrors = [];


    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return array_merge(parent::getLongOpts(), [self::OPT_LONG_BACKUP_DIR]);
    }


    /**
     * @inheritDoc
     * @throws Throwable
     */
    public function run(): void
    {
        $this->logSync("Started at" . (Alaress_Date_Format::now())->getDatabase());
        try {
            $this->execute();
            if (count($this->notFatalErrors)) {
                $this->logSync("Completed with errors at " . (Alaress_Date_Format::now())->getDatabase() . implode(',', $this->notFatalErrors));
            } else {
                $this->logSync("Successfully completed at " . (Alaress_Date_Format::now())->getDatabase());
            }
        } catch (Throwable $e) {
            $this->logSync("Errored at " . (Alaress_Date_Format::now())->getDatabase() . " - {$e->getMessage()}");
            throw $e;
        }
    }


    /**
     * Uploads new backup and deletes any old stale backups
     *
     * @throws Flysystem\RootViolationException
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    private function execute(): void
    {
        $fileSystemAwsS3 = (new FilesystemFactory())->buildAwsS3FileSystem();

        $dailyBackupDir = $this->getOpt(self::OPT_LONG_BACKUP_DIR);
        if (!$dailyBackupDir || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dailyBackupDir)) {
            $message = sprintf(
                "Unable to copy daily backup directory '%s'. Please provide option '--%s'",
                $dailyBackupDir,
                rtrim(self::OPT_LONG_BACKUP_DIR, ':')
            );
            $this->getLogger()->error($message);
            throw new UnexpectedValueException("Daily backup dir appeared invalid: '$dailyBackupDir'");
        }
        $this->addNewBackup($fileSystemAwsS3, $dailyBackupDir);
        $this->deleteStaleBackups($fileSystemAwsS3, $dailyBackupDir);
    }


    /**
     * Updates the config that tracks the state of the last execution of this tool
     *
     * @param string $message
     * @throws UnexpectedValueException
     * @throws PDOException
     * @throws MapperException
     */
    private function logSync(string $message): void
    {
        $config = ConfigMapper::getInstance()->findOneByColumn(ConfigMapper::COL_NAME, self::CONFIG_DR_SYNC_STATUS);
        $config->setValue($message);
        ConfigMapper::getInstance()->save($config);
    }


    /**
     * Copies a daily backup directory from local to S3
     *
     * @param Filesystem $fileSystemAwsS3
     * @param string     $dailyBackupDir
     * @throws UnexpectedValueException
     */
    private function addNewBackup(Filesystem $fileSystemAwsS3, string $dailyBackupDir): void
    {
        $storageDir = rtrim($this->getConfig()->get('storage_dir'), '\\/');
        $backupDir  = dirname($storageDir) . "/backups/daily/$dailyBackupDir";

        if (!is_dir($backupDir)) {
            $message = "Daily backup dir does not appear to be a directory: '$backupDir'";
            $this->getLogger()->error($message);
            throw new UnexpectedValueException($message);
        }
        $itr = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($backupDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        /** @var SplFileInfo $fileInfo */
        foreach ($itr as $fileInfo) {
            $localPath = $fileInfo->getPathname();

            $s3Path = str_replace($backupDir, "backups/$dailyBackupDir", $localPath);

            $file = new Flysystem\File($fileSystemAwsS3, $s3Path);
            if ($file->exists()) {
                if ($file->getSize() === filesize($localPath)) {
                    $this->getLogger()->info("File already in S3 and size matches: '$s3Path'");
                    continue;
                }
                $this->getLogger()->info("File in S3 but size different, re-uploading: '$s3Path'");
                if ($this->perform()) {
                    $file->delete();
                }
            }

            $fp = fopen($localPath, 'rb');
            if (!is_resource($fp)) {
                $message = "Cannot open file '$localPath' for reading";
                $this->getLogger()->error($message);
                $this->notFatalErrors[] = $message;
                continue;
            }

            if (!$this->perform() || $file->writeStream($fp)) {
                $this->getLogger()->info("Successfully uploaded file: '$s3Path'");
            } else {
                $message = "Unable to upload file: '$s3Path'";
                $this->getLogger()->error($message);
                $this->notFatalErrors[] = $message;
            }
        }
    }


    /**
     * Deletes stale backups from S3
     *
     * @param Filesystem $fileSystemAwsS3
     * @param string     $dailyBackupDir
     * @throws InvalidArgumentException
     * @throws Flysystem\RootViolationException
     * @throws UnexpectedValueException
     */
    private function deleteStaleBackups(Filesystem $fileSystemAwsS3, string $dailyBackupDir): void
    {
        $backupsFromS3 = $this->getBackupsInS3($fileSystemAwsS3);

        foreach ((new BackupRetention())->getDatesToDelete($backupsFromS3) as $backupToDelete) {
            if ($backupToDelete === $dailyBackupDir) {
                // Safety check, this should never happen
                throw new UnexpectedValueException("Attempted to delete current backup '$backupToDelete'");
            }
            if ($fileSystemAwsS3->deleteDir(self::PREFIX_BACKUPS . $backupToDelete)) {
                $this->getLogger()->info("Successfully deleted backup '$backupToDelete'");
            } else {
                $this->getLogger()->error("Unable to delete backup '$backupToDelete'");
            }
        }
    }


    /**
     * Get the daily backup dir from S3
     *
     * @param Filesystem $fileSystemAwsS3
     * @return string[]
     */
    private function getBackupsInS3(Filesystem $fileSystemAwsS3): array
    {
        $backups = [];
        foreach ($fileSystemAwsS3->listContents(self::PREFIX_BACKUPS) as $content) {
            if ($content['type'] === 'dir') {
                $backups[] = $content['basename'];
            }
        }

        return $backups;
    }
}
