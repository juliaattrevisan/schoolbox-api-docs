<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage\RelationalDelete;

use Eportfolio\CoverPhotoMapper;
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
     * @var \Schoolbox\Resource\Storage\Mapper
     */
    private $storageMapper;

    /**
     * RelationalDelete constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger           = $logger;

        $this->storageMapper    = new \Schoolbox\Resource\Storage\Mapper();

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
            foreach ($this->collectors as $collector) {
                $collector->remove($hash, $performDryRun);
            }
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
        return [
            // Blog
            new Deletable(
                \Schoolbox\Blog\Homepage\Image\Mapper::getInstance(),
                $this->logger
            ),

            // ePortfolio
            new Deletable(
                \Schoolbox\Blog\Eportfolio\Image\Mapper::getInstance(),
                $this->logger
            ),
            new Deletable(
                CoverPhotoMapper::getInstance(),
                $this->logger,
                'imagehash'
            ),
            new \Component\Eportfolio\RelationalDelete(
                \Schoolbox\Resource\My\File\Mapper::getInstance(),
                $this->logger
            ),

            // Pastoral
            new Deletable(
                \Schoolbox\Pastoral\Attachment\Mapper::getInstance(),
                $this->logger
            ),
            new Deletable(
                \Schoolbox\Pastoral\UploadQueue\Mapper::getInstance(),
                $this->logger
            ),

            // My File
            new Deletable(
                \Schoolbox\Resource\My\File\Mapper::getInstance(),
                $this->logger
            ),

            // Dropbox
            new Deletable(
                \Schoolbox\Dropbox\Mapper::getInstance(),
                $this->logger
            ),

            // Plagscan
            new \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\RelationalDelete(
                \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper::getInstance(),
                $this->logger
            ),

            // Assessments
            new Deletable(
                \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance(),
                $this->logger
            ),

            // Submission box file
            new Deletable(
                \Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Mapper::getInstance(),
                $this->logger,
                'storage_id'
            ),

            // Submission return file
            new Deletable(
                \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Mapper::getInstance(),
                $this->logger
            ),

            // Task
            new Deletable(
                \Schoolbox\Assessment\Course\Task\File\Mapper::getInstance(),
                $this->logger,
                'storage_id'
            ),

            // Video Queue
            new Deletable(
                \Schoolbox\Video\QueueMapper::getInstance(),
                $this->logger
            ),

            // File
            new Deletable(
                \Schoolbox\Resource\File\Mapper::getInstance(),
                $this->logger
            ),

            // News
            new Deletable(
                \Schoolbox\Comms\News\File\Mapper::getInstance(),
                $this->logger,
                'storage_id'
            ),
        ];
    }

    /**
     * @return array
     */
    private function generateNullables()
    {
        return [
            new Nullable(
                \Schoolbox\Comms\News\Mapper::getInstance(),
                $this->logger,
                ['featureImage'],
                'feature_image_hash'
            ),

            // Folder
            new Nullable(
                \Schoolbox\Resource\Folder\Mapper::getInstance(),
                $this->logger,
                ['coverImage'],
                'image_hash'
            ),

            // Tile
            new Nullable(
                \Schoolbox\Tile\Mapper::getInstance(),
                $this->logger,
                ['backgroundImage'],
                'background_image_hash'
            ),

            // Resource
            new Nullable(
                \Schoolbox\ResourceBooking\Item\Mapper::getInstance(),
                $this->logger,
                ['image'],
                'image_hash'
            ),

            // Socialstream
            new Nullable(
                \SocialStream\PostMapper::getInstance(),
                $this->logger,
                ['cacheHash'],
                'cache_hash'
            ),
            new Nullable(
                \SocialStream\PostMapper::getInstance(),
                $this->logger,
                ['thumbnail'],
                'thumbnail',
                '/storage/image.php?size=maxsize&hash='
            ),
            new Nullable(
                \SocialStream\PostMapper::getInstance(),
                $this->logger,
                ['url'],
                'url',
                '/storage/image.php?size=maxsize&hash='
            ),

            // User
            new Nullable(
                \Schoolbox\UserMapper::getInstance(),
                $this->logger,
                ['portrait'],
                'portrait_hash'
            ),

            // Badge
            new Nullable(
                \Schoolbox\Learning\Badge\Mapper::getInstance(),
                $this->logger,
                ['image'],
                'image_hash'
            ),

            // Evidence
            new Nullable(
                \Schoolbox\Learning\Moment\Evidence\Mapper::getInstance(),
                $this->logger,
                ['storage'],
                'hash'
            ),

            // EOTC Meta data
            new \EOTC\RelationalDelete(
                \EOTC\EventMetadataMapper::getInstance(),
                $this->logger
            ),
        ];
    }
}
