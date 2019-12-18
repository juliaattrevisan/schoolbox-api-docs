<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Lists;

use Alaress\Database\Predicate\InvalidColumnException;
use InvalidArgumentException;
use Schoolbox\Comms\News;
use Schoolbox\Comms\News\Audience\ArticleResolver;
use Schoolbox\Comms\News\Audience\Segment\Strategy;

/**
 * Responsible for consistently finding a set of articles for a folder's feed
 */
class FolderFeed
{
    /** @var ArticleResolver */
    private $articleResolver;

    /**
     * @param ArticleResolver $articleResolver
     */
    public function __construct(ArticleResolver $articleResolver)
    {
        $this->articleResolver = $articleResolver;
    }


    /**
     * Returns the set of ids of articles that were audienced to the specified folder.
     *
     * Optionally filters by topic and state
     *
     * @param int         $folderId
     * @return array
     * @throws InvalidColumnException
     * @throws InvalidArgumentException
     */
    public function find(int $folderId): array
    {
        return $this->articleResolver->findArticlesForAudience(
            Strategy\Folder::getEntityType(),
            $folderId,
            News\Audience\Model::RELATIONSHIP_SELF
        );
    }
}
