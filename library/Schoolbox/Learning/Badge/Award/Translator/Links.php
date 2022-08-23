<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award\Translator;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Schoolbox\Learning\Badge\Award;
use Schoolbox\User;

/**
 * Class Links
 *
 * Set up the _links node with a bunch of permission-based urls that
 * $user can access
 *
 * @package Schoolbox\Learning\Badge\Award\Translator
 */
class Links
{

    /**
     * The router object, as required to generate a link to content.
     */
    private Router $router;


    /**
     * The authorizer object, as required to determine whether a user is
     * authorized to access a link.
     */
    private Authorizer $authorizer;


    /**
     * The user who is being authorized to access a link.
     */
    private User $currentUser;


    /**
     * Constructor
     *
     * @param Router     $router
     * @param Authorizer $authorizer
     * @param User       $currentUser
     */
    public function __construct(Router $router, Authorizer $authorizer, User $currentUser)
    {
        $this->router      = $router;
        $this->authorizer  = $authorizer;
        $this->currentUser = $currentUser;
    }


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
            'learningBadgeAward.awardAsAchievement',
            [
                'badgeId' => $award->getBadge()->getId(),
            ]
        );

        /* End point for updating properties about an Awarding
         */
        $translation['awardUpdate'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.getAwardUpdate',
            [
                'awardId' => $award->getId()
            ]
        );

        return $translation;
    }


    /**
     * Helper function for outputting a route, only if it is authorized.
     *
     * @param string $route
     * @param array  $params
     * @return string|null
     * @noinspection PhpDocMissingThrowsInspection Should never happen as `$route` should never not be defined
     */
    private function getRouteIfAuthorized(string $route, array $params): ?string
    {
        if ($this->authorizer->isPathAuthorizedForUser($route, $params, $this->currentUser)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            return $this->router->generate($route, $params);
        }
        return null;
    }
}
