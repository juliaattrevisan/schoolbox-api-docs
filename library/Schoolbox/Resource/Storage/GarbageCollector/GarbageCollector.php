<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GarbageCollector;

use Eportfolio\CoverPhotoMapper;
use Psr\Log\LoggerInterface;
use Schoolbox\Blog\Homepage\Image\Mapper;

class GarbageCollector implements IGarbageCollector
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
     * GarbageCollector constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger           = $logger;

        $this->storageMapper    = new \Schoolbox\Resource\Storage\Mapper();

        $this->collectors = [
            // Blog
            new \Schoolbox\Blog\Homepage\Image\GarbageCollector(Mapper::getInstance(), $this->logger),

            // ePortfolio
            new \Schoolbox\Blog\Eportfolio\Image\GarbageCollector(\Schoolbox\Blog\Eportfolio\Image\Mapper::getInstance(), $this->logger),
            new \Eportfolio\GarbageCollector(CoverPhotoMapper::getInstance(), $this->logger),
            new \Component\Eportfolio\GarbageCollector(\Schoolbox\Resource\My\File\Mapper::getInstance(), $this->logger),

            // Pastoral
            new \Schoolbox\Pastoral\Attachment\GarbageCollector(\Schoolbox\Pastoral\Attachment\Mapper::getInstance(), $this->logger),
            new \Schoolbox\Pastoral\UploadQueue\GarbageCollector(\Schoolbox\Pastoral\UploadQueue\Mapper::getInstance(), $this->logger),

            // My File
            new \Schoolbox\Resource\My\File\GarbageCollector(\Schoolbox\Resource\My\File\Mapper::getInstance(), $this->logger),

            // News - The first line may be deleted when the old news has been deprecated
            new \Schoolbox\News\GarbageCollector(\Schoolbox\News\ArticleMapper::getInstance(), $this->logger),
            new \Schoolbox\Comms\News\GarbageCollector(\Schoolbox\Comms\News\Mapper::getInstance(), $this->logger),

            // Folder
            new \Schoolbox\Resource\Folder\GarbageCollector(\Schoolbox\Resource\Folder\Mapper::getInstance(), $this->logger),

            // Tile
            new \Schoolbox\Tile\GarbageCollector(\Schoolbox\Tile\Mapper::getInstance(), $this->logger),

            // Dropbox
            new \Schoolbox\Dropbox\GarbageCollector(\Schoolbox\Dropbox\Mapper::getInstance(), $this->logger),

            // Plagscan
            new \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\GarbageCollector(\Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper::getInstance(), $this->logger),

            // Resource
            new \Schoolbox\ResourceBooking\Item\GarbageCollector(\Schoolbox\ResourceBooking\Item\Mapper::getInstance(), $this->logger),

            // Socialstream
            new \SocialStream\GarbageCollector(\SocialStream\PostMapper::getInstance(), $this->logger),

            // Assessments
            new \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\GarbageCollector(\Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance(), $this->logger),
            // Submission box file
            new \Schoolbox\Assessment\Component\SubmissionBox\SingleFile\GarbageCollector(\Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Mapper::getInstance(), $this->logger),
            // Submission return file
            new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\GarbageCollector(\Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Mapper::getInstance(), $this->logger),

            // User
            new \Schoolbox\GarbageCollector(\Schoolbox\UserMapper::getInstance(), $this->logger),

            // Video Queue
            new \Schoolbox\Video\GarbageCollector(\Schoolbox\Video\QueueMapper::getInstance(), $this->logger),

            // File
            new \Schoolbox\Resource\File\GarbageCollector(\Schoolbox\Resource\File\Mapper::getInstance(), $this->logger),

            // Storage (Final query)
            new \Schoolbox\Resource\Storage\GarbageCollector(\Schoolbox\Resource\Storage\Mapper::getInstance(), $this->logger),
        ];
    }

    /**
     * @inheritDoc
     */
    public function remove(string $hash, bool $performDryRun)
    {
        if ($performDryRun) {
            $this->logger->debug('Executing in dry run mode...');
        }

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
}
