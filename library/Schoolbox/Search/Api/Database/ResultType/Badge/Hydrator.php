<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Badge;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Learning\Badge\Model as BadgeModel;
use Schoolbox\Learning\Badge\Mapper as BadgeMapper;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\User;

/**
 * Hydrator for badge results.
 */
class Hydrator extends BaseHydrator
{
    /**
     * Base type for this type of result.
     */
    public const BASE_TYPE = 'badge';

    /**
     * Constructor
     *
     * @param BadgeMapper $badgeMapper Used to fetch badge for hydration.
     */
    public function __construct(
        private BadgeMapper $badgeMapper
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
        // Find all badges that match $matches.
        $acceptedMatches = array_filter($matches, [$this, 'accepts']);

        $ids = array_map(fn (RawResult $match) => $match->id, $acceptedMatches);
        if (empty($ids)) {
            return [];
        }

        // Find the matching badges.
        $badges = $this->badgeMapper->findByColumn('id', $ids);

        // And map to a full result.
        $fullResults = array_map(
            function (RawResult $match) use ($keyword, $badges) {
                $badge = $badges->getBy('id', $match->id);
                if ($badge instanceof BadgeModel) {
                    // For other searched items, permission checks are done here.
                    // But badges are not linked to folders, so no permission checks.

                    return new FullResult(
                        $match,
                        $match->id,
                        $badge->getName(), // The closest thing to a title for a badge is badge.name.
                        '', // There is no 'view specific badge' page, so blank string here.
                        $match->type,
                        $match->relevance,
                        $badge->getUpdatedAt(),
                        null, // A badge is not linked to any folder.
                        $badge->getCategory()->getName(), // Meta.  Context line for the search result.
                        $badge->getImage(),
                        'badge',
                        $this->truncateAroundKeyword($keyword, $badge->getBody(), self::DESCRIPTION_LENGTH),
                        $badge->getCreatedBy(),
                        [], // Context.  Empty since no linked folder.
                        [], // Links.  None for badges.
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
