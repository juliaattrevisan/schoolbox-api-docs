<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage;

use Alaress\Database as AlaressDatabase;
use Aws\S3\S3Client;
use InvalidArgumentException;
use PDO;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\FilesystemFactory;
use UnexpectedValueException;

/**
 * Deletes storage transforms from
 * * local disk
 * * S3 storage
 * * storage_transform table
 *
 * WARNING!! THIS TOOL HARD_DELETES THESE FILES, THERE IS LIKELY NO BACKUP OF AWS S3, DO NOT RUN OR MODIFY
 *           UNLESS YOU REALLY KNOW WHAT YOU ARE DOING!!
 *
 *           ALWAYS DO A DRY-RUN FIRST AND EYEBALL THE OUTPUT TO ENSURE ONLY TRANSFORMS ARE BEING DELETED
 */
class DeleteTransforms extends CliTool\App
{
    public function runAsRoot(): ?bool
    {
        // We are deleting from the local file system
        return true;
    }

    /**
     * @throws AlaressDatabase\Exception
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function run(): void
    {
        $this->confirmExecution();
        $countLocalTransforms = $this->deleteTransformFilesLocal();
        $countAwsS3Transforms = $this->deleteTransformFilesInS3();
        $countTransformTuples = $this->truncateStorageTransformTable();

        $verb = $this->perform() ? "Deleted" : "Would delete";
        $this->getLogger()->notice(sprintf("$verb %d local transform files", $countLocalTransforms));
        $this->getLogger()->notice(sprintf("$verb %d AWS S3 transform files", $countAwsS3Transforms));
        $this->getLogger()->notice(sprintf("$verb %d storage transform tuples", $countTransformTuples));
    }


    /**
     * Prompts the user to confirm they wish to delete the transforms
     */
    private function confirmExecution(): void
    {
        $red   = self::COLOUR_RED;
        $white = self::COLOUR_WHITE;
        fwrite(STDOUT, "\n\n\t{$red}WARNING, THIS TOOL WILL HARD-DELETE TRANSFORM FILES FROM STORAGE, ONLY PROCEED IF YOU ARE DECOMMISSIONING THIS SERVER\n");
        fwrite(STDOUT, "\n\n\t         YOU MUST PERFORM A DRY RUN FIRST AND VISUALLY INSPECT THE OUTPUT TO ENSURE IT IS VALID{$white}\n\n\n");
        $confirmInput = "DELETE FOR " . $this->getConfig()->get('site_school_code');
        $input        = $this->readLine("Do you wish to continue? Type '$confirmInput' to confirm, anything else will cancel: ");
        if ($input !== $confirmInput) {
            fwrite(STDOUT, "Tool cancelled\n");
            exit(CliTool\Kernel::EXIT_STATUS_OK);
        }
    }


    /**
     * Load all transform files in local filesystem
     *
     * @throws UnexpectedValueException
     * @return int
     */
    private function deleteTransformFilesLocal(): int
    {
        $this->getLogger()->notice("Deleting local storage transform files...");
        $exec = $this->perform() ? "-exec rm -v {} +" : "";
        $cmd  = sprintf("find %s -type f -iname '*-*' $exec", $this->getConfig()->get('storage_dir'));
        exec($cmd, $output, $returnCode);
        if ($returnCode) {
            throw new UnexpectedValueException("Unable to execute command: $cmd");
        }
        $transformFilesLocal = [];
        foreach ($output as $line) {
            if ($this->perform()) {
                $line = trim(str_replace("removed ", '', $line), "'");
            }
            $transformFilesLocal[] = $line;
        }
        $verb = $this->perform() ? "Deleted" : "Would have deleted";
        $this->getLogger()->notice(sprintf("$verb %d local transforms", count($transformFilesLocal)));
        foreach ($transformFilesLocal as $file) {
            $this->getLogger()->debug("$verb $file");
        }
        return count($transformFilesLocal);
    }


    /**
     * Load all transform files in AWS S3
     * @throws InvalidArgumentException
     * @return int
     */
    private function deleteTransformFilesInS3(): int
    {
        $this->getLogger()->notice("Deleting AWS S3 storage transform files...");
        $bucketName = trim($this->getConfig()->get('otfs_s3_bucket') ?? "");
        $s3Client   = $this->buildS3Client($bucketName);
        if (!$s3Client) {
            return 0;
        }

        if ($s3Client->getBucketVersioning(['Bucket' => $bucketName])->get('Status') !== 'Enabled') {
            return $this->deleteFromNonVersionControlledBucket($s3Client, $bucketName);
        }
        return $this->deleteFromVersionControlledBucket($s3Client, $bucketName);
    }


    /**
     * Deletes storage transform files a non-versioned S3 Bucket
     *
     * @param S3Client $s3Client
     * @param string   $bucketName
     * @return int
     */
    private function deleteFromNonVersionControlledBucket(S3Client $s3Client, string $bucketName): int
    {
        $verb      = $this->perform() ? "Deleting" : "Would delete";
        $iterator  = $s3Client->getIterator('ListObjects', [
            'Bucket' => $bucketName,
            'Prefix' => FilesystemFactory::PREFIX_STORAGE,
        ]);
        $filesInS3 = [];
        foreach ($iterator as $i => $object) {
            if (preg_match("#/([0-9a-f]{40})-[\w-]+$#", $object['Key'])) {
                $filesInS3[] = $object['Key'];
            }
        }
        // Need to do this in chunks of 1000
        $chunkSize  = 1000;
        $chunkCount = ceil(count($filesInS3) / $chunkSize);
        for ($i = 0; $i < $chunkCount; $i++) {
            $first = $i * $chunkSize;
            $last  = min(($i + 1) * $chunkSize - 1, count($filesInS3) - 1);
            $chunk = array_slice($filesInS3, $first, $last - $first + 1);

            $objects = [];
            foreach ($chunk as $key) {
                $objects[] = ['Key' => $key];
                $this->getLogger()->debug("$verb s3 transform. Key: '{$key}'");
            }

            if ($this->perform()) {
                $s3Client->deleteObjects([
                    'Bucket' => $bucketName,
                    'Delete' => [
                        'Objects' => $objects,
                        'Quiet'   => true,
                    ],
                ]);
            }
        }
        return count($filesInS3);
    }


    /**
     * Deletes storage transform files a versioned S3 Bucket
     *
     * @param S3Client $s3Client
     * @param string   $bucketName
     * @return int
     */
    private function deleteFromVersionControlledBucket(S3Client $s3Client, string $bucketName): int
    {
        $verb        = $this->perform() ? "Deleting" : "Would delete";
        $allVersions = [];
        $keyMarker   = $versionMarker = '';
        do {
            $args = [
                'Bucket'    => $bucketName,
                'Prefix'    => FilesystemFactory::PREFIX_STORAGE,
                'KeyMarker' => $keyMarker,
            ];
            if ($keyMarker) {
                $args['VersionIdMarker'] = $versionMarker;
            }
            $result   = $s3Client->listObjectVersions($args);
            $versions = $result->get('Versions') ?? [];
            foreach ($versions as $version) {
                if (preg_match("#/([0-9a-f]{40})-[\w-]+$#", $version['Key'])) {
                    $allVersions[] = ['Key' => $version['Key'], 'VersionId' => $version['VersionId']];
                }
            }
            $keyMarker     = $result->get('NextKeyMarker');
            $versionMarker = $result->get('NextVersionIdMaker');
        } while ($keyMarker);

        // Need to do this in chunks of 1000
        $chunkSize  = 1000;
        $chunkCount = ceil(count($allVersions) / $chunkSize);
        for ($i = 0; $i < $chunkCount; $i++) {
            $first = $i * $chunkSize;
            $last  = min(($i + 1) * $chunkSize - 1, count($allVersions) - 1);
            $chunk = array_slice($allVersions, $first, $last - $first + 1);

            foreach ($chunk as $object) {
                $this->getLogger()->debug("$verb s3 transform. Key: '{$object['Key']}', VersionId: '{$object['VersionId']}'");
            }
            if ($this->perform()) {
                $s3Client->deleteObjects([
                    'Bucket' => $bucketName,
                    'Delete' => [
                        'Objects' => $chunk,
                        'Quiet'   => true,
                    ],
                ]);
            }
        }
        return count($allVersions);
    }


    /**
     * Attempts to build and return a S3Client. Returns NULL if config is empty
     *
     * @param string $bucketName
     * @return S3Client|null
     * @throws InvalidArgumentException
     */
    private function buildS3Client(string $bucketName): ?S3Client
    {
        $s3Region    = trim($this->getConfig()->get('otfs_s3_region') ?? "");
        $s3Version   = trim($this->getConfig()->get('otfs_s3_version') ?? "");
        $s3AccessKey = trim($this->getConfig()->get('otfs_s3_cred_key') ?? "");
        $s3SecretKey = trim($this->getConfig()->get('otfs_s3_cred_secret') ?? "");

        $badConfig = empty($s3AccessKey) || empty($s3SecretKey) || empty($s3Region) || empty($s3Version) || empty($bucketName);
        if ($badConfig) {
            $this->getLogger()->warning("S3 config appears invalid, skipping");
            return null;
        }

        return new S3Client([
            'credentials' => [
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey,
            ],
            'region'      => $s3Region,
            'version'     => $s3Version,
        ]);
    }


    /**
     * Truncates the storage_transform table
     *
     * @throws AlaressDatabase\Exception
     * @return int
     */
    private function truncateStorageTransformTable(): int
    {
        $hashes = $this->getDatabaseInternal()->executeQuery("SELECT hash, transform FROM storage_transform")->fetchAll(PDO::FETCH_NUM);

        $verb = $this->perform() ? "Deleting" : "Would delete";
        $this->getLogger()->notice(sprintf("$verb %d storage_transform tuples", count($hashes)));
        foreach ($hashes as [$hash, $transform]) {
            $this->getLogger()->debug("$verb storage_transform tuple. Hash: '$hash', Transform: '$transform'");
        }

        if ($this->perform()) {
            $this->getDatabaseInternal()->execute("TRUNCATE storage_transform");
        }
        return count($hashes);
    }
}
