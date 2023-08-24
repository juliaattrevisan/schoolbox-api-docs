<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Link;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Link as LinkModel;
use Schoolbox\Resource\LinkMapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;

/**
 * Hydrator for link results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'link';

    /**
     * Constructor
     *
     * @param LinkMapper $linkMapper Used to fetch links for hydration.
     * @param Access      $access     Used to determine if the current user may view this page.
     */
    public function __construct(
        private LinkMapper $linkMapper,
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
        // Find all links that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        // TODO make this part generic
        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching links.
        // TODO make generic: have 'id' column as a property of class, or use mappers
        $links = $this->linkMapper->findByColumn('id', $ids);

        // And map to a full result
        $fullResults = array_map(
            function (RawResult $match) use ($links, $viewer) {
                $link = $links->getBy('id', $match->id);
                if ($link instanceof LinkModel) {
                    // Check if user has access to link's folder
                    $linkAccess = $this->access->getFolderAccess($viewer, $link->getFolder()->getId());
                    if ($linkAccess < UserAccess::ACCESS_READ) {
                        return null;
                    }
                    if ($linkAccess < UserAccess::ACCESS_WRITE && $link->isHidden()) {
                        return null;
                    }
                    return new FullResult(
                        $match,
                        $match->id,
                        $link->getComment(),
                        $this->buildBaseUrl()
                             ->withPath('send.php')
                             ->withQuery(http_build_query(['id' => $link->getId()]))
                             ->__toString(),
                        $match->type,
                        $match->relevance,
                        $link->getModified(),
                        $link->getFolder(),
                        null,
                        // Image? show thumbnail, otherwise show mimetype thumb
                        null,
                        'link',
                        $link->getDescription(),
                        // Should this be modified by instead?
                        $link->getOwner(),
                        // Context: link's folder
                        [$link->getFolder(),],
                        [],
                    );
                }

                return null;
            },
            $matches
        );

        // Filter out anything missing
        // TODO make generic
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }
}
