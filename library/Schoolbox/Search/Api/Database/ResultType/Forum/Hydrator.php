<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Forum;

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
     * @param PostMapper   $postMapper   Used to fetch forum posts for hydration.
     * @param ThreadMapper $threadMapper Used to check whether a thread is a component.
     * @param Access       $access       Used to determine if the current user may view this page.
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

                // Has a post in this thread already been added to results?
                static $threadsInResults = [];
                if (isset($threadsInResults[$postThread->getId()])) {
                    return null;
                }
                $threadsInResults[$postThread->getId()] = true;

                $nMatchingPostsInThread = $nMatchingPostsByThreadId[$postThread->getId()] ?? 0;
                $matchingPostString = $nMatchingPostsInThread > 1 ? "(+$nMatchingPostsInThread more matching posts)" : '';
                $postBodyTextOnly = $this->htmlToText($post->getBody());

                // This formerly eliminated matches on invisible HTML tags
                // (eg. 'style='): now that the search index is prebuilt without
                // these tags, this is no longer necessary.

                return new FullResult(
                    $match,
                    $match->id,
                    $postThread->getTitle(),
                    $this->buildBaseUrl()
                         ->withPath("/forum/thread/{$postThread->getId()}")
                         ->withFragment("{$post->getId()}")
                         ->__toString(),
                    $match->type,
                    'Forum',
                    $match->relevance,
                    $post->getModified(),
                    $threadFolder,
                    "Posted {$post->getModified()->getRelativeTime()} by {$post->getOwner()->getFullName()} $matchingPostString",
                    null,
                    'forum',
                    $this->truncateAroundKeyword($keyword, $postBodyTextOnly) ?? $this->truncate($postBodyTextOnly),
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
     * Note that for the purposes of this function, a post matches the keyword if and only if the post body contains the keyword.
     * I.e. this function does not consider matches on the thread title (nor should it).
     * So it is possible that a thread whose title matches the keyword will have no matching posts, and in that case,
     * the threadId passed to this function will not be present in the returned array.
     * (Adding all the missing keys to the return array before returning it would be a bad idea because there is no
     *  guarantee that the $threadIds passed to this function exist in the database.)
     * (See search criteria defined in Forum/IndexBuilder.php.)
     *
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
            $params['filterFromDate'] = $filter->fromDate->getDatabaseDate(); // IMPORTANT: There must be no time component on this date.
        }
        if ($filter->toDate) {
            $whereParts[]           = "$datePart <= :filterToDate";
            $params['filterToDate'] = $filter->toDate->getDatabaseDate(); // IMPORTANT: There must be no time component on this date.
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
            AND deleted_at IS NULL
            GROUP BY threadID',
            $params
        )->fetchColumnArray('count', 'threadID');
    }
}
