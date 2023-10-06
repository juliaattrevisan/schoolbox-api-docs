<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Forum;

use Alaress\Html\ToText;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Forum\Post as PostModel;
use Schoolbox\Forum\PostMapper;
use Schoolbox\Forum\ThreadMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
use Schoolbox\User;

/**
 * Hydrator for forum post results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'forum';

    /**
     * Constructor
     *
     * @param PostMapper $postMapper Used to fetch forum posts for hydration.
     * @param ThreadMapper $threadMapper Used to check whether a thread is a component.
     * @param Access $access Used to determine if the current user may view this page.
     */
    public function __construct(
        private PostMapper $postMapper,
        private ThreadMapper $threadMapper,
        private Access $access,
    ) {
    }

    /**
     * @inheritdoc
     */
    protected function getBaseType(): string
    {
        return self::BASE_TYPE;
    }

    /**
     * @inheritdoc
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
    {
        // Find all forum posts that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $postIds = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($postIds)) {
            return [];
        }

        // Find the matching forum posts.
        $posts = $this->postMapper->findByColumn('postID', $postIds);

        // Find the parent threadID of each forum post.
        $threadIds = [];
        foreach ($posts as $post) {
             $threadIds[] = $post->getThread()->getId();
        }

        $nMatchingPostsByThreadId = $this->getNumberOfMatchingPostsIndexedByThreadId($threadIds, $keyword, $filter);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($posts, $viewer, $keyword, $nMatchingPostsByThreadId) {
                $post = $posts->getBy('id', $match->id);
                if (!($post instanceof PostModel)) {
                    return null;
                }

                $postThread = $post->getThread();
                $threadFolder = $postThread->getCategory();

                if (!$this->threadMapper->isComponent($threadFolder->getId())) {
                    return null;
                }

                // Check if user has access to post's folder
                try {
                    $postAccess = $this->access->getFolderAccess($viewer, $threadFolder->getId());
                } catch (MapperException) {
                    $postAccess = 0;
                }
                if ($postAccess < UserAccess::ACCESS_READ) {
                    return null;
                }

                $nMatchingPostsInThread = $nMatchingPostsByThreadId[$postThread->getId()];

                $postBody = (new ToText($post->getBody()))->getText();
                return new FullResult(
                    $match,
                    $match->id,
                    $postThread->getTitle() . (
                        " ($nMatchingPostsInThread matching post" . (($nMatchingPostsInThread === 1) ? "" : "s") . " in this thread)"
                    ),
                    $this->buildBaseUrl()
                         ->withPath("/forum/thread/{$postThread->getId()}")
                         ->withFragment("{$post->getId()}")
                         ->__toString(),
                    $match->type,
                    $match->relevance,
                    $post->getModified(),
                    $threadFolder,
                    $post->getOwner()->getFullName(),
                    null,
                    'forum',
                    $this->truncateAroundKeyword($keyword, $postBody) ?? $this->truncate($postBody),
                    $post->getOwner(),
                    [$threadFolder], // Context: post's folder
                    [],
                );
            },
            $matches
        );

        // Filter out anything missing.
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }

    /**
     * @param array  $threadIds
     * @param string $keyword
     * @param SearchFilter\DataObject $filter
     * @return array
     */
    private function getNumberOfMatchingPostsIndexedByThreadId(array $threadIds, string $keyword, SearchFilter\DataObject $filter): array
    {
        // The $whereParts added here are the same as those added in BaseQueryParamBuilder->buildMatchQueryParams().
        $whereParts = [];
        $params = [];

        $datePart    = 'modified'; // Must match Forum/QueryParamBuilder->buildDateExpression();
        $creatorPart = 'userID';   // Must match Forum/QueryParamBuilder->buildCreatorExpression();
        // Note $folderPart is not set here because Forum/QueryParamBuilder->buildFolderExpression() returns blank.

        if ($filter->fromDate) {
            $whereParts[]             = "$datePart >= :filterFromDate";
            $params['filterFromDate'] = $filter->fromDate->getDatabase();
        }
        if ($filter->toDate) {
            $whereParts[]           = "$datePart <= :filterToDate";
            $params['filterToDate'] = $filter->toDate->getDatabase();
        }
        if ($filter->creator) {
            $whereParts[]            = "$creatorPart = :filterCreator";
            $params['filterCreator'] = $filter->creator->getId();
        }
        // Note $filter->folder is not used here because Forum/QueryParamBuilder->buildFolderExpression() returns blank.
        // Note $filter->types is not used here because that this function is being called implies the forum type has
        // been selected, and that is all we care about.

        $whereParts[] = 'body LIKE :filterKeywordLike';
        $params[':filterKeywordLike'] = "%{$keyword}%";

        return $this->threadMapper->getDatabase()->executeQuery(
            'SELECT threadID, COUNT(*) AS `count`
            FROM forumPost
            WHERE threadID IN (' . implode(',', array_map('intval', $threadIds)) . ')
            AND ' . implode(' AND ', $whereParts) . '
            AND deleted="false"
            GROUP BY threadID',
            $params
        )->fetchColumnArray('count', 'threadID');
    }
}
