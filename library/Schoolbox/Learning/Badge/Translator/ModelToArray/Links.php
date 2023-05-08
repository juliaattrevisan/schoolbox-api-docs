<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator\ModelToArray;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\Learning\Badge\Model;

/**
 * Translator for loading links for the badge.
 */
class Links extends AbstractLinks
{
    /**
     * @param Model $badge
     * @return array
     */
    public function __invoke(Model $badge): array
    {
        $translation = [
            '_links' => [],
        ];

        // Award and set goal routes
        $translation['_links']['awardAsAchievement'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.awardAsAchievement',
            ['badgeId' => $badge->getId(),]
        );
        $translation['_links']['setAsGoal'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.setAsGoal',
            ['badgeId' => $badge->getId(),]
        );
        $translation['_links']['setAsGoalForSelf'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.setAsGoalForSelf',
            ['badgeId' => $badge->getId(),]
        );

        // Modify/delete routes (not yet implemented)
//        $translation['_links']['modifyForm'] = $this->getRouteIfAuthorized(
//            'learningBadge.getModifyForm',
//            [
//                'badgeId' => $badge->getId(),
//            ]
//        );
//        $translation['_links']['put'] = $this->getRouteIfAuthorized(
//            'learningBadge.putBadge',
//            [
//                'badgeId' => $badge->getId(),
//            ]
//        );
//        $translation['_links']['delete'] = $this->getRouteIfAuthorized(
//            'learningBadge.deleteBadge',
//            [
//                'badgeId' => $badge->getId(),
//            ]
//        );


        return $translation;
    }
}
