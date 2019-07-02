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
 * Delete storage files and it's relationships with other entites.
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

        $this->collectors = [
            // Blog
            new Deletable(\Schoolbox\Blog\Homepage\Image\Mapper::getInstance(), $this->logger),

            // ePortfolio
            new Deletable(\Schoolbox\Blog\Eportfolio\Image\Mapper::getInstance(), $this->logger),
            new Deletable(CoverPhotoMapper::getInstance(), $this->logger, 'imagehash'),
            new \Component\Eportfolio\RelationalDelete(\Schoolbox\Resource\My\File\Mapper::getInstance(), $this->logger),

            // Pastoral
            new Deletable(\Schoolbox\Pastoral\Attachment\Mapper::getInstance(), $this->logger),
            new Deletable(\Schoolbox\Pastoral\UploadQueue\Mapper::getInstance(), $this->logger),

            // My File
            new Deletable(\Schoolbox\Resource\My\File\Mapper::getInstance(), $this->logger),

            // News - The first line may be deleted when the old news has been deprecated
            new \Schoolbox\News\RelationalDelete(\Schoolbox\News\ArticleMapper::getInstance(), $this->logger),
            new \Schoolbox\Comms\News\RelationalDelete(\Schoolbox\Comms\News\Mapper::getInstance(), $this->logger),

            // Folder
            new \Schoolbox\Resource\Folder\RelationalDelete(\Schoolbox\Resource\Folder\Mapper::getInstance(), $this->logger),

            // Tile
            new \Schoolbox\Tile\RelationalDelete(\Schoolbox\Tile\Mapper::getInstance(), $this->logger),

            // Dropbox
            new Deletable(\Schoolbox\Dropbox\Mapper::getInstance(), $this->logger),

            // Plagscan
            new \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\RelationalDelete(\Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper::getInstance(), $this->logger),

            // Resource
            new \Schoolbox\ResourceBooking\Item\RelationalDelete(\Schoolbox\ResourceBooking\Item\Mapper::getInstance(), $this->logger),

            // Socialstream
            new \SocialStream\Cache\RelationalDelete(\SocialStream\PostMapper::getInstance(), $this->logger),
            new \SocialStream\Post\Thumbnail\RelationalDelete(\SocialStream\PostMapper::getInstance(), $this->logger),
            new \SocialStream\Post\Url\RelationalDelete(\SocialStream\PostMapper::getInstance(), $this->logger),

            // Assessments
            new Deletable(\Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance(), $this->logger),
            // Submission box file
            new Deletable(\Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Mapper::getInstance(), $this->logger, 'storage_id'),
            // Submission return file
            new Deletable(\Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Mapper::getInstance(), $this->logger),

            // User
            new \Schoolbox\RelationalDelete(\Schoolbox\UserMapper::getInstance(), $this->logger),

            // Video Queue
            new Deletable(\Schoolbox\Video\QueueMapper::getInstance(), $this->logger),

            // File
            new Deletable(\Schoolbox\Resource\File\Mapper::getInstance(), $this->logger),

            // Storage (Do NOT move this anywhere. Must be the last item that gets executed due to the table relationships.)
            new Deletable(\Schoolbox\Resource\Storage\Mapper::getInstance(), $this->logger)
        ];
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
}
