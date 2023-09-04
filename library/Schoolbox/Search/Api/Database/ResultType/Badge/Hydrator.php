<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Badge;

use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
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
     * @param Access      $access     Used to determine if the current user may view this page.
     */
    public function __construct(
        private BadgeMapper $badgeMapper,
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
            function (RawResult $match) use ($badges, $viewer) {
                $badge = $badges->getBy('id', $match->id);
                if ($badge instanceof BadgeModel) {
                    // Check if user has access to badge's folder
//                    $badgeAccess = $this->access->getFolderAccess($viewer, $badge->getFolder()->getId());
//                    if ($badgeAccess < UserAccess::ACCESS_READ) {
//                        return null;
//                    }
//                    if ($badgeAccess < UserAccess::ACCESS_WRITE && $badge->isHidden()) {
//                        return null;
//                    }

                    return new FullResult(
                        $match,
                        $match->id,
                        $badge->getName(), // The closest thing to a title for a badge is badge.name.
                        '', // TODO: What to put here?  There is no 'view specific badge' page.
                        $match->type,
                        $match->relevance,
                        $badge->getUpdatedAt(),
                        null, // TODO: What to do about folder?
                        null,
                        $badge->getImage(),
                        'badge',
                        $badge->getBody(), // The closest thing to a description for a badge is badge.body.
                        $badge->getCreatedBy(),
                        [], // TODO: Context.  Normally put the ID of a linked folder here, but no linked folders.
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
