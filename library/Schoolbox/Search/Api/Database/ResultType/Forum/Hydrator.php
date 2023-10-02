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
    public function hydrate(string $keyword, array $matches, User $viewer): array
    {
        // Find all forum posts that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching forum posts.
        $posts = $this->postMapper->findByColumn('postID', $ids);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($posts, $viewer, $keyword) {
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

                $db = $this->threadMapper->getDatabase();
                $nMatchingPostsInThread = $db->executeQuery(
                    'SELECT COUNT(*)
                         FROM forumPost
                         WHERE threadID=?
                         AND deleted="false"
                         AND body LIKE ?',
                         [$postThread->getId(), "%{$keyword}%"]
                )->fetchColumn();

                $postBody = (new ToText($post->getBody()))->getText();
                return new FullResult(
                    $match,
                    $match->id,
                    $postThread->getTitle() . (
                        " ($nMatchingPostsInThread matching post" . (($nMatchingPostsInThread === 1) ? "": "s") . " in this thread)"
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
}
