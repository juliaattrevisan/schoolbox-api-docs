<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

use Aws\S3\S3Client;
use Schoolbox\Application\CliTool\App;
use Schoolbox\Application\CliTool\Kernel;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\FilesystemFactory;

class Seeder extends App
{
    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var string
     */
    private $bucket;

    /**
     * @var string
     */
    private $storageDir;

    /**
     * @var string
     */
    private $storageAdapter;

    /**
     * @var array
     */
    private $transcodeImages = [];

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['h'];
    }

    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return ['help'];
    }

    /**
     * Hands off execution over to the App
     */
    public function run(): void
    {
        $this->getLogger()->info('Storage transform data seeder');

        if ($this->getOpt('h') || $this->getOpt('help')) {
            $this->printHelp();
            exit(Kernel::EXIT_STATUS_OK);
        }

        $this->buildDependencies()->prepareData()->seed();
    }


    /**
     * @return Seeder
     */
    private function buildDependencies(): Seeder
    {
        $config = $this->getConfig();

        $this->s3Client = new S3Client(
            [
                'credentials' => [
                    'key'    => trim($config->get('otfs_s3_cred_key') ?? ''),
                    'secret' => trim($config->get('otfs_s3_cred_secret') ?? ''),
                ],
                'region'      => trim($config->get('otfs_s3_region') ?? ''),
                'version'     => trim($config->get('otfs_s3_version') ?? ''),
            ]
        );

        $this->bucket         = trim($config->get('otfs_s3_bucket') ?? '');
        $this->storageDir     = trim($config->get('storage_dir'));
        $this->storageAdapter = trim($config->get(ConfigMapper::NAME_OTFS_ADAPTER));

        $this->createStorageTransformSchema();

        return $this;
    }

    /**
     * Create storage_transform table IF IT DOESN'T EXISTS yet.
     *
     * Columns: hash, transform and size
     * Also add constraint with storage table.
     */
    private function createStorageTransformSchema(): void
    {
        $this->getDatabaseInternal()->executeQuery("CREATE TABLE IF NOT EXISTS `storage_transform` (
            `hash` VARCHAR(100) NULL COLLATE 'ascii_general_ci',
            `transform` VARCHAR(100) NULL,
            `size` INT(11) NULL,
            UNIQUE INDEX `unique_hash_transform` (`hash` ASC, `transform` ASC),
            INDEX `index_hash` (`hash` ASC)
            -- CONSTRAINT `fk_storage_transform` FOREIGN KEY (`hash`) REFERENCES `storage` (`hash`) ON DELETE CASCADE ON UPDATE CASCADE
        )");
    }

    /**
     * @return Seeder
     */
    private function prepareData(): Seeder
    {
        // Get all images in storage table
        $getImagesQuery = "SELECT s.hash, aft.mime FROM storage s
                        LEFT JOIN allowedFileExtension afe ON s.ext = afe.extension
                        LEFT JOIN allowedFileTypes aft ON aft.fileTypeID = afe.fileTypeID
                        WHERE aft.mime LIKE 'image/%'
                        ";

        $images = $this->getDatabaseInternal()->executeQuery($getImagesQuery)->fetchAll();

        foreach ($images as $key => $image) {
            // Investigate file directories for available transcodes
            $this->generateListsOfTranscodedImages($image);
        }

        $this->getLogger()->notice("Image transcode count: " . count($this->transcodeImages));

        return $this;
    }

    /**
     * @param array $image
     */
    private function generateListsOfTranscodedImages(array $image): void
    {
        $path = sprintf("%s%s", chunk_split($image['hash'], 5, '/'), $image['hash']);

        if ($this->storageAdapter === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3) {
            $this->getTranscodedImagesInS3Fs(FilesystemFactory::PREFIX_STORAGE . $path);
        } else {
            $this->getTranscodedImagesInLocalFs($this->storageDir . "/" . $path);
        }
    }

    /**
     * @param string $filePath
     */
    private function getTranscodedImagesInLocalFs(string $filePath): void
    {
        if (!file_exists($filePath)) {
            $this->getLogger()->error("Storage file '$filePath' not found in local filesystem, skipping");
            return;
        }
        $dir = opendir(dirname($filePath));

        while (($entry = readdir($dir)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (strpos($entry, '-') !== false) {
                $file = explode('-', $entry, 2);
                if (count($file) > 1) {
                    $this->transcodeImages[] = [
                        'hash'      => $file[0],
                        'transform' => $file[1],
                        'size'      => (int)filesize(dirname($filePath) . '/' . $entry),
                    ];
                    $this->getLogger()->info($entry);
                }
            }
        }

        closedir($dir);
    }

    /**
     * @param string $filePath
     */
    private function getTranscodedImagesInS3Fs(string $filePath): void
    {
        $itemsInFolder = $this->s3Client->listObjects([
            'Bucket' => $this->bucket,
            'Prefix' => dirname($filePath),
        ]);

        if (count($itemsInFolder['Contents']) > 0) {
            foreach ($itemsInFolder['Contents'] as $item) {
                $basename = basename($item['Key']);
                if (strpos($basename, '-') !== false) {
                    $file = explode('-', $basename, 2);
                    if (count($file) > 1) {
                        $this->transcodeImages[] = [
                            'hash'      => $file[0],
                            'transform' => $file[1],
                            'size'      => (int)$item['Size'],
                        ];
                    }
                    $this->getLogger()->info($basename);
                }
            }
        }
    }

    private function seed(): void
    {
        if ($this->perform()) {
            $this->getLogger()->info('Running in perform mode (-p): new records will be inserted in the storage_transform table (IF ANY)');

            $insertQuery = $this->getDatabaseInternal()->prepareQuery("
                INSERT INTO `storage_transform` (`hash`, `transform`, `size`, `state`, `updated_at`)
                VALUES (:hash_1, :transform_1, :size_1, 'done', NOW())
                ON DUPLICATE KEY UPDATE
                    hash =:hash_2,
                    transform = :transform_2,
                    size = :size_2
            ");

            foreach ($this->transcodeImages as $key => $image) {
                $verb = $this->checkIfRecordExists($image['hash'], $image['transform']) ? "Updating" : "Inserting";
                $this->getLogger()->info("$verb image transform. Hash: {$image['hash']}, cacheKey: {$image['transform']}, size: {$image['size']}");

                $insertQuery->execute([
                    'hash_1'      => $image['hash'],
                    'transform_1' => $image['transform'],
                    'size_1'      => $image['size'],
                    'hash_2'      => $image['hash'],
                    'transform_2' => $image['transform'],
                    'size_2'      => $image['size'],
                ]);
            }
        } else {
            $this->getLogger()->notice('Running in dry-run mode: use -p to insert record to database');
        }
    }

    /**
     * @param string $hash
     * @param string $transform
     *
     * @return bool
     */
    private function checkIfRecordExists(string $hash, string $transform): bool
    {
        $query = $this->getDatabaseInternal()->prepareQuery("SELECT 1 FROM `storage_transform` WHERE `hash` = :hash AND `transform` = :transform");
        $query->bindInputs(['hash' => $hash, 'transform' => $transform,])->execute();

        return (bool)$query->rowCount();
    }

    /**
     * Print a help message of available options
     */
    private function printHelp(): void
    {
        echo "Options:
    -h, --help
        Print help message and exit.
    -p, --perform
        If set, the tool will persist data to `storage_transform` table
";
    }
}
