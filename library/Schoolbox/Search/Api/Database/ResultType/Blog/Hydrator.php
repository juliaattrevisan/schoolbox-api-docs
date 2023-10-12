<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Blog;

use Alaress\Html\ToText;
use Schoolbox\Blog\Homepage\Post\Mapper as BlogMapper;
use Schoolbox\Blog\Homepage\Post\Model as Blog;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

/**
 */
class Hydrator extends BaseHydrator
{
    /**
     * This hydrator's base type.
     */
    public const BASE_TYPE = 'blog';

    /**
     * Constructor
     *
     * @param BlogMapper $blogMapper
     * @param Access     $access
     */
    public function __construct(
        private BlogMapper $blogMapper,
        private Access $access
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all news articles which match $matches
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);
        $blogIds         = array_map(fn(RawResult $match) => $match->id, $acceptedMatches);
        if (empty($blogIds)) {
            return [];
        }

        // Find the given blog posts
        $blogs = $this->blogMapper->findByColumn('id', $blogIds)->indexBy('id');

        // And map them to a full result
        $fullResults = array_map(
            function (RawResult $match) use ($blogs, $keyword, $viewer) {
                $blog = $blogs->offsetGet($match->id);
                if (!$blog instanceof Blog) {
                    return null;
                }
                $access = $this->access->getFolderAccess($viewer, $blog->getFolder()->getId());
                if ($access < UserAccess::ACCESS_READ) {
                    return null;
                }
                $blogBody = (new ToText($blog->getBody()))->getText();
                return new FullResult(
                    $match,
                    $match->id,
                    $match->title,
                    $this->buildBaseUrl()
                         ->withPath("/blog/{$blog->getId()}")
                         ->__toString(),
                    $match->type,
                    $match->relevance,
                    $blog->getCreatedAt(),
                    $blog->getFolder(),
                    "Posted {$blog->getCreatedAt()->getRelativeTime()} by {$blog->getOwner()->getFullName()}",
                    null,
                    self::BASE_TYPE,
                    $this->truncateAroundKeyword($keyword, $blogBody) ?? $this->truncate($blogBody),
                    $blog->getOwner(),
                    [],
                    []
                );
            },
            $matches,
        );
        return array_filter($fullResults, fn(?FullResult $x) => isset($x));
    }
}
