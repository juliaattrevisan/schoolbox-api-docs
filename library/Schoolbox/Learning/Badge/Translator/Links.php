<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Learning\Badge;
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
     * Prefix generator: for attaching a prefix to the badge image link.
     */
    private PrefixGenerator $prefixGenerator;


    /**
     * Constructor
     *
     * @param Router          $router
     * @param Authorizer      $authorizer
     * @param User            $currentUser
     * @param PrefixGenerator $prefixGenerator
     */
    public function __construct(
        Router $router,
        Authorizer $authorizer,
        User $currentUser,
        PrefixGenerator $prefixGenerator
    ) {
        $this->router          = $router;
        $this->authorizer      = $authorizer;
        $this->currentUser     = $currentUser;
        $this->prefixGenerator = $prefixGenerator;
    }


    /**
     * @param Badge\Model $badge
     * @return array
     */
    public function __invoke(Badge\Model $badge): array
    {
        $translation = [];

        /* Endpoint for updating this badge
         */
        $translation['update'] = $this->getRouteIfAuthorized(
            'learningBadge.getUpdateForm',
            [
                'badgeId' => $badge->getId()
            ]
        );

        /* Endpoint for deleting the badge
        */
        $translation['delete'] = $this->getRouteIfAuthorized(
            'learningBadge.deleteBadge',
            [
                'badgeId' => $badge->getId()
            ]
        );

        /* All the people who have received this badge as an achievement
         */
        $translation['achievementRecipients'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.getAchievementRecipients',
            [
                'badgeId' => $badge->getId(),
            ]
        );

        /* All the people who have received this badge as a goal
         */
        $translation['goalRecipients'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.getGoalRecipients',
            [
                'badgeId' => $badge->getId(),
            ]
        );

        /* Endpoint for withdrawing the awarding of this achievement
         */
        $translation['achievementWithdraw'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.postAchievementWithdraw',
            [
                'badgeId' => $badge->getId(),
            ]
        );

        /* Endpoint for unsetting the goal
         */
        $translation['goalWithdraw'] = $this->getRouteIfAuthorized(
            'learningBadgeAward.postGoalWithdraw',
            [
                'badgeId' => $badge->getId(),
            ]
        );

        // Icon: only if the badge has an icon
        $translation['icon'] = $badge->getIcon()
            ? ($this->prefixGenerator->generate() . $badge->getIcon())
            : null
        ;


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
