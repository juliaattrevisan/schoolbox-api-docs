<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Badge;

use Alaress\Html\ToText;
use Schoolbox\Cdn\PrefixGenerator;
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
        private BadgeMapper $badgeMapper,
        private PrefixGenerator $prefixGenerator
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

                    // Work out image path for badge
                    $image = null;
                    if ($badge->getIcon()) {
                        $image = $this->prefixGenerator->generate() . $badge->getIcon();
                    }
                    if ($badge->getImage() && $badge->getImage()->getMimetype()->isImage()) {
                        $image = $this->buildStorageImageUrl($badge->getImage())->__toString();
                    }
                    $badgeBody = (new ToText($badge->getBody()))->getText();
                    return new FullResult(
                        $match,
                        $match->id,
                        $badge->getName(), // The closest thing to a title for a badge is badge.name.
                        $this->buildBaseUrl()
                             ->withPath("/learning/badge/{$badge->getId()}/update")
                             ->__toString(),
                        $match->type,
                        $match->relevance,
                        $badge->getUpdatedAt(),
                        null, // A badge is not linked to any folder.
                        $badge->getCategory()->getName(), // Meta.  Context line for the search result.
                        $image,
                        'badge',
                        $this->truncateAroundKeyword($keyword, $badgeBody) ?? $this->truncate($badgeBody),
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
