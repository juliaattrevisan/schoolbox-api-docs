<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Sanitizer;

use League\Flysystem\FileNotFoundException;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

class CliSanitizer extends CliTool\App
{
    // type - exif, svg
    public const OPT_LONG_TYPE = 'type';

    public const TYPE_EXIF = 'exif';
    public const TYPE_SVG  = 'svg';

    /** type of files to sanitize */
    public string $sanitizeType;

    private const TYPE_MIME = [
        self::TYPE_EXIF => ['image/jpeg', 'image/tiff', 'image/pjpeg'],
        self::TYPE_SVG  => ['image/svg+xml'],
    ];

    // tables which are referring hash
//    private const TABLES = [
//        'task_file'                          => 'storage_id',
//        'submission_return_file'             => 'hash',
//        'submission_box_file'                => 'storage_id',
//        'student_submission_file'            => 'hash',
//        'eportfolio_component_filelist_item' => 'hash',
//        'blog_image'                         => 'hash',
//        'comms_news_article_file'            => 'storage_id',
//        'comms_news_article'                 => 'feature_image_hash',
//        'dropbox'                            => 'hash',
//        'sportsfixtures_import_errors'       => 'hash',
//        'plagscan_document'                  => 'hash',
//        'badge'                              => 'image_hash',
//        'evidence'                           => 'hash',
//        'mobile_app'                         => 'cert_hash',
//        'pastoral_attachment'                => 'hash',
//        'pastoral_upload_queue'              => 'hash',
//        'file'                               => 'hash',
//        'myFile'                             => 'hash',
//        'folder'                             => 'image_hash',
//        'storage_transform'                  => 'hash',
//        'resource'                           => 'image_hash',
//        'ups_log'                            => 'hash',
//        'tile'                               => 'background_image_hash',
//        'user'                               => 'hash',
//        'video_queue'                        => 'hash',
//    ];

    /**
     * @inheritDoc
     */
    public function run(): void
    {
        if (!key_exists($this->sanitizeType, self::TYPE_MIME)) {
            $this->getLogger()->warning('Set sanitize type.');
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $mime = self::TYPE_MIME[$this->sanitizeType];

        if ($this->sanitizeType === self::TYPE_EXIF && !$this->getConfig()->get('remove_image_location_data')) {
            $this->getLogger()->info('Remove Image Location Data is disabled in config; exiting');
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        if ($this->perform()) {
            $this->getLogger()->info('Running in perform mode (-p): changes will be written to filesystem');
        } else {
            $this->getLogger()->info('Running in dry-run mode: use -p to write changes to filesystem');
        }
        $storageMapper  = new StorageMapper();
        $storageManager = $storageMapper->getManager();
        $exifFiles      = $storageMapper->findByColumn('allowedFileTypes.mime', $mime);

        /** @var Storage $exifFile */
        foreach ($exifFiles as $exifFile) {
            $originalHash = $exifFile->getHash();

            // sanitize
            try {
                $result = $storageManager->sanitize($exifFile, !$this->perform());
            } catch (FileNotFoundException $e) {
                $this->getLogger()->debug("Failed to find {$exifFile->getFilename()} ({$exifFile->getHash()}), skipping");
                continue;
            }

            if ($result) {
                $this->getLogger()->debug(($this->perform() ? 'Sanitized: ' : 'Will be sanitized: ') . "{$exifFile->getFilename()} ({$exifFile->getHash()})");
            } else {
                $this->getLogger()->debug(($this->perform() ? 'Skipped: ' : 'Will be skipped: ') . "{$exifFile->getFilename()} ({$exifFile->getHash()})");
                continue;
            }
            if (!$this->perform()) {
                continue;
            }

            // move files to new storage .. we probably do not want to do this
            $newHash = $exifFile->getHash();
//            $newPath = $storageMapper->getManager()::getPathFromHash($newHash);
//            $exifFile->setPath($newPath);
//            $originalDir  = str_replace($originalHash, '', $originalPath);
//
//            $originalFiles = $storageManager->getMountManager()->listContents(Manager::FS_STORAGE . '://' . $originalDir);
//            foreach ($originalFiles as $originalFile) {
//                $newFile = str_replace($originalPath, $newPath, $originalFile['path']);
//                $res     = $storageManager->getMountManager()->move(
//                    Manager::FS_STORAGE . '://' . $originalFile['path'],
//                    Manager::FS_STORAGE . '://' . $newFile
//                );
//                $this->getLogger()->debug(($res ? 'Succeeded' : 'Failed') . " to move {$originalFile['path']} to {$newFile}");
//            }

            $statement = $storageMapper->getDatabase()->prepare(
                'UPDATE storage SET hash_after_sanitization = :newHash
                  WHERE hash = :originalHash '
            );
            $statement->execute(
                [
                    'newHash'      => $newHash,
                    'originalHash' => $originalHash,
                ]
            );
        }
    }

    /**
     * Type of files to sanitize
     * @param string $sanitizeType
     * @return $this
     */
    public function setSanitizeType(string $sanitizeType): self
    {
        $this->sanitizeType = $sanitizeType;
        return $this;
    }
}
