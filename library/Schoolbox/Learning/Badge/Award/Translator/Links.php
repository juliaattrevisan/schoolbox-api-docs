<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award\Translator;

use Schoolbox\API\Translator\AbstractLinks;
use Schoolbox\Learning\Badge\Award;

/**
 * Class Links
 *
 * Set up the _links node with a bunch of permission-based urls that
 * $user can access
 *
 * @package Schoolbox\Learning\Badge\Award\Translator
 */
class Links extends AbstractLinks
{
    /**
     * @param Award\Model $award
     * @return array
     */
    public function __invoke(Award\Model $award): array
    {
        $translation = [];

        /* Endpoint for withdrawing the awarding of this achievement
         */
        $translation['achievementWithdraw'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.postAchievementWithdraw',
            [
               'badgeId' => $award->getBadge()->getId(),
            ]
        );

        /* Endpoint for unsetting the goal
         */
        $translation['goalWithdraw'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.postGoalWithdraw',
            [
                'badgeId' => $award->getBadge()->getId(),
            ]
        );

        /* End point for awarding goal
        */
        $translation['goalAward'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.postAwardAchieve',
            [
                'awardId' => $award->getId(),
            ]
        );

        /* End point for updating properties about an Awarding
         */
        $translation['awardUpdate'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.getAwardUpdate',
            [
                'awardId' => $award->getId(),
                'award'   => $award,
            ]
        );

        return $translation;
    }
}
