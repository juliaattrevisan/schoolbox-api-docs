<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Api\Database\ResultType\Badge;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Html\ToText;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Learning\Badge\Model as BadgeModel;
use Schoolbox\Learning\Badge\Mapper as BadgeMapper;
use Schoolbox\Role;
use Schoolbox\Search\Api\Cursor\RawResult;
use Schoolbox\Search\Api\Hydrator\BaseHydrator;
use Schoolbox\Search\Api\Hydrator\FullResult;
use Schoolbox\Search\Api\SearchFilter;
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
        private PrefixGenerator $prefixGenerator,
        private Generator $routeGenerator,
        private Authorizer $authorizer
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
     * @throws RouteNotFound
     */
    public function hydrate(string $keyword, SearchFilter\DataObject $filter, array $matches, User $viewer): array
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
            function (RawResult $match) use ($keyword, $badges, $viewer) {
                $badge = $badges->getBy('id', $match->id);
                if (!($badge instanceof BadgeModel)) {
                    return null;
                }

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
                    '',  // There is no 'view specific badge' page, so blank string here.
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
                    $this->getSearchResultLinks($badge, $viewer)
                );
            },
            $matches
        );

        // Filter out anything missing.
        return array_filter($fullResults, fn (?FullResult $x) => isset($x));
    }

    /**
     * Return an array of sub-arrays, each sub-array containing info for one link.
     * The links will be in alphabetical order of title.
     *
     * @param BadgeModel $badge
     * @param User       $viewer
     * @return array array<array{"title": string, "icon": string, "url": string, "action"?: string}>
     * @throws RouteNotFound
     */
    private function getSearchResultLinks(BadgeModel $badge, User $viewer): array
    {
        $links = [];

        $canCreateBadgesRule = new Rule\HasRolePermission(Role::PERMISSION_CREATE_BADGES, Role::STAFF);
        $canCreateBadges = $canCreateBadgesRule->authorize([], $this->authorizer->getUser());

        if ($canCreateBadges) {
            $links[] = [
                'title' => 'Award Achievement',
                'icon' => 'icon-badge',
                'url' => '',
                'action' => 'awardAchievementModal' // This asks the client to create a modal like the one used on the learning/badge page.
            ];                                      // The modal prompts the user to supply the students to whom the achievement should be awarded.
        }

        if ($canCreateBadges) {
            $links[] = [
                'title' => 'Set Goal',
                'icon' => 'icon-goal',
                'url' => '',
                'action' => 'setGoalModal' // This asks the client to create a modal like the one used on the learning/badge page.
            ];                             // The modal prompts the user to supply the students for whom the goal should be set.
        }

        $route = 'learningBadge.getUpdateForm';
        $params = ['badgeId' => $badge->getId(), 'badge' => $badge];
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $viewer)) {
            $links[] = [
                'title' => 'Modify',
                'icon' => 'icon-modify',
                'url' => $this->routeGenerator->generate($route, $params),
            ];
        }

        return $links;
    }
}
