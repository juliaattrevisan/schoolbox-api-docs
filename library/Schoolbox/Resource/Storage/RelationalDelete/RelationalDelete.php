<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\RelationalDelete;

use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Resource\Storage;
use Psr\Log\LoggerInterface;

/**
 * Delete storage files and it's relationships with other entities.
 *
 * @package Schoolbox\Resource\Storage\RelationalDelete
 */
class RelationalDelete implements IRelationalDelete
{
    /**
     * @var array
     */
    private $collectors;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Storage\Mapper
     */
    private $storageMapper;

    /**
     * RelationalDelete constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger, Storage\Mapper $storageMapper)
    {
        $this->logger           = $logger;
        $this->storageMapper    = $storageMapper;

        $this->collectors       = array_merge(
            $this->generateDeletables(),
            $this->generateNullables(),
            [
            // Storage (Do NOT move this anywhere. Must be the last item that gets executed due to the table relationships.)
            new Deletable(\Schoolbox\Resource\Storage\Mapper::getInstance(), $this->logger)
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun): void
    {
        $debugMessage = 'Executing in delete/unlink mode...';

        if ($performDryRun) {
            $debugMessage = 'Executing in dry run mode...';
        }

        $this->logger->debug($debugMessage);

        if ($this->validateIfHashExists($hash)) {
            $this->storageMapper->getDatabase()->beginTransaction();
            try {
                foreach ($this->collectors as $collector) {
                    $collector->remove($hash, $performDryRun);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Fatal exception. Rolling back database changes");
                $this->storageMapper->getDatabase()->rollback();
                throw $e;
            }
            $this->storageMapper->getDatabase()->commit();
        } else {
            $this->logger->error('Unable to find: ' . $hash);
        }
    }

    /**
     * Check if hash do exists in storage by hash
     *
     * @param string $hash
     *
     * @return bool
     */
    private function validateIfHashExists(string $hash): bool
    {
        if ($this->storageMapper->findByColumn('hash', $hash)->first()) {
            return true;
        }

        return false;
    }

    /**
     * @return array
     */
    private function generateDeletables()
    {
        // Ordered by referencing (table_name,column_name)
        return [
            // blog_image.hash
            new Deletable(
                \Schoolbox\Blog\Homepage\Image\Mapper::getInstance(),
                $this->logger
            ),

            // comms_news_article_file.storage_id
            new Deletable(
                \Schoolbox\Comms\News\File\Mapper::getInstance(),
                $this->logger,
                'storage_id'
            ),

            // dropbox.hash
            new Deletable(
                \Schoolbox\Dropbox\Mapper::getInstance(),
                $this->logger
            ),

            // eotc_metadata_file.storage_id
            new \EOTC\RelationalDelete(
                \EOTC\EventMetadataMapper::getInstance(),
                $this->logger,
                \Database::getInstance(),
            ),

            // eportfolio_component_blog_image.hash
            new Deletable(
                \Schoolbox\Blog\Eportfolio\Image\Mapper::getInstance(),
                $this->logger
            ),

            // eportfolio_coverphoto.imagehash
            new Deletable(
                \Eportfolio\CoverPhotoMapper::getInstance(),
                $this->logger,
                'imagehash'
            ),

            // file.hash
            new Deletable(
                \Schoolbox\Resource\File\Mapper::getInstance(),
                $this->logger
            ),

            // myFile.hash => storage.hash
            // eportfolio_component_filelist_item.file_id => myFile.id
            new \Schoolbox\Resource\My\File\RelationalDelete(
                \Schoolbox\Resource\My\File\Mapper::getInstance(),
                $this->logger,
                \Component\Eportfolio\FileListMapper::getInstance(),
            ),

            // pastoral_attachment.hash
            new Deletable(
                \Schoolbox\Pastoral\Attachment\Mapper::getInstance(),
                $this->logger
            ),

            // pastoral_upload_queue.hash
            new Deletable(
                \Schoolbox\Pastoral\UploadQueue\Mapper::getInstance(),
                $this->logger
            ),

            // plagscan_document.storage_hash
            new Deletable(
                Plagscan\Mapper::getInstance(),
                $this->logger,
                'storage_hash',
            ),

            // student_submission_file.hash
            // turnitinreport.student_submission_file_id
            // plagscan_document.student_submission_file_id
            // student_submission_file_kami.student_submission_file_id
            new \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\RelationalDelete(
                \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance(),
                $this->logger,
                Turnitin\File\Mapper::getInstance(),
                Plagscan\Mapper::getInstance(),
                Kami\Mapper::getInstance(),
            ),

            // submission_box_file.storage_id
            new Deletable(
                \Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Mapper::getInstance(),
                $this->logger,
                'storage_id'
            ),

            // submission_return_file.hash
            new Deletable(
                \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Mapper::getInstance(),
                $this->logger
            ),

            // task_file.storage_id
            new Deletable(
                \Schoolbox\Assessment\Course\Task\File\Mapper::getInstance(),
                $this->logger,
                'storage_id'
            ),

            // video_queue.hash
            new Deletable(
                \Schoolbox\Video\QueueMapper::getInstance(),
                $this->logger
            ),
        ];
    }

    /**
     * @return array
     */
    private function generateNullables()
    {
        // Order by table_name,column_name
        return [
            // badge.image_hash
            new Nullable(
                \Schoolbox\Learning\Badge\Mapper::getInstance(),
                $this->logger,
                ['image'],
                'image_hash'
            ),

            // comms_news_article.feature_image_hash
            new Nullable(
                \Schoolbox\Comms\News\Mapper::getInstance(),
                $this->logger,
                ['featureImage'],
                'feature_image_hash'
            ),

            // evidence.hash
            new Nullable(
                \Schoolbox\Learning\Moment\Evidence\Mapper::getInstance(),
                $this->logger,
                ['storage'],
                'hash'
            ),

            // folder.image_hash
            new Nullable(
                \Schoolbox\Resource\Folder\Mapper::getInstance(),
                $this->logger,
                ['coverImage'],
                'image_hash'
            ),

            // mobile_app.cert_hash
            new Nullable(
                \Schoolbox\MobileApp\ServiceMapper::getInstance(),
                $this->logger,
                ['certificate'],
                'cert_hash'
            ),

            // resource.image_hash
            new Nullable(
                \Schoolbox\ResourceBooking\Item\Mapper::getInstance(),
                $this->logger,
                ['image'],
                'image_hash'
            ),

            // socialstream_post.cache_hash
            new Nullable(
                \SocialStream\PostMapper::getInstance(),
                $this->logger,
                ['cacheHash'],
                'cache_hash'
            ),

            // socialstream_post.thumbnail
            new Nullable(
                \SocialStream\PostMapper::getInstance(),
                $this->logger,
                ['thumbnail'],
                'thumbnail',
                '/storage/image.php?size=maxsize&hash='
            ),

            // socialstream_post.url
            new Nullable(
                \SocialStream\PostMapper::getInstance(),
                $this->logger,
                ['url'],
                'url',
                '/storage/image.php?size=maxsize&hash='
            ),

            // tile. background_image_hash
            new Nullable(
                \Schoolbox\Tile\Mapper::getInstance(),
                $this->logger,
                ['backgroundImage'],
                'background_image_hash'
            ),

            // user.portrait_hash
            new Nullable(
                \Schoolbox\UserMapper::getInstance(),
                $this->logger,
                ['portrait'],
                'portrait_hash'
            ),
        ];
    }
}
