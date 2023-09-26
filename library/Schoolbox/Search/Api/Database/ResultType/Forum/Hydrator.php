<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Forum;

use Alaress\Html\ToText;
use Schoolbox\Collection;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Forum\Post as PostModel;
use Schoolbox\Forum\PostMapper;
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
     * @param Access     $access     Used to determine if the current user may view this page.
     */
    public function __construct(
        private PostMapper $postMapper,
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
                if ($post instanceof PostModel) {
                    $postThread = $post->getThread();
                    $postFolder = $postThread->getCategory();

                    // Check if user has access to post's folder
                    try {
                        $postAccess = $this->access->getFolderAccess($viewer, $postFolder->getId());
                    } catch (MapperException) {
                        $postAccess = 0;
                    }
                    if ($postAccess < UserAccess::ACCESS_READ) {
                        return null;
                    }
                    $postBody = (new ToText($post->getBody()))->getText();
                    return new FullResult(
                        $match,
                        $match->id,
                        $postThread->getTitle(),
                        $this->buildBaseUrl()
                             ->withPath("/forum/thread/{$post->getThread()->getId()}")
                             ->withFragment("{$post->getId()}")
                             ->__toString(),
                        $match->type,
                        $match->relevance,
                        $post->getModified(),
                        $postFolder,
                        $post->getOwner()->getFullName(),
                        null,
                        'forum',
                        $this->truncateAroundKeyword($keyword, $postBody) ?? $this->truncate($postBody),
                        $post->getOwner(),
                        [$postFolder], // Context: post's folder
                        [],
                    );
                }

                return null;
            },
            $matches
        );

        // Filter out anything missing.
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }
}
