<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\GarbageCollector;

use Eportfolio\CoverPhotoMapper;
use Schoolbox\Blog\Homepage\Image\Mapper;
use Schoolbox\Engagement\SocialStream;

class GarbageCollector implements IGarbageCollector
{
    private $collectors;

    public function __construct()
    {
        $this->collectors = [
            // Blog
            new \Schoolbox\Blog\Homepage\Image\GarbageCollector(Mapper::getInstance()),

            // ePortfolio
            new \Schoolbox\Blog\Eportfolio\Image\GarbageCollector(\Schoolbox\Blog\Eportfolio\Image\Mapper::getInstance()),
            new \Eportfolio\GarbageCollector(CoverPhotoMapper::getInstance()),
            new \Component\Eportfolio\GarbageCollector(\Schoolbox\Resource\My\File\Mapper::getInstance()),

            // Pastoral
            new \Schoolbox\Pastoral\Attachment\GarbageCollector(\Schoolbox\Pastoral\Attachment\Mapper::getInstance()),

            // My File
            new \Schoolbox\Resource\My\File\GarbageCollector(\Schoolbox\Resource\My\File\Mapper::getInstance()),

            // News
            new \Schoolbox\News\GarbageCollector(\Schoolbox\News\ArticleMapper::getInstance()),
            new \Schoolbox\Comms\News\GarbageCollector(\Schoolbox\Comms\News\Mapper::getInstance()),

            // Folder
            new \Schoolbox\Resource\Folder\GarbageCollector(\Schoolbox\Resource\Folder\Mapper::getInstance()),

            // Tile
            new \Schoolbox\Tile\GarbageCollector(\Schoolbox\Tile\Mapper::getInstance()),

            // Dropbox
            new \Schoolbox\Dropbox\GarbageCollector(\Schoolbox\Dropbox\Mapper::getInstance()),

            // Plagscan
            new \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\GarbageCollector(\Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper::getInstance()),
            new \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Migration\GarbageCollector(\Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Migration\Mapper::getInstance()),
            // Must be the last to execute
            new \Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\GarbageCollector(\Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper::getInstance()),

            // Socialstream
            new \SocialStream\GarbageCollector(\SocialStream\PostMapper::getInstance()),

            // Submission box file
            new \Schoolbox\Assessment\Component\SubmissionBox\SingleFile\GarbageCollector(\Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Mapper::getInstance()),

            // Submission return file
            new \Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\GarbageCollector(\Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork\SingleFile\Mapper::getInstance()),

            // Video Queue
            new \Schoolbox\Video\GarbageCollector(\Schoolbox\Video\QueueMapper::getInstance()),

            // File
            new \Schoolbox\Resource\File\GarbageCollector(\Schoolbox\Resource\File\Mapper::getInstance()),

            // Storage (Final query)
            new \Schoolbox\Resource\Storage\GarbageCollector(\Schoolbox\Resource\Storage\Mapper::getInstance()),
        ];
    }

    /**
     * @inheritDoc
     */
    public function remove(string $hash)
    {
        foreach ($this->collectors as $collector) {
            $collector->remove($hash);
        }
    }
}
