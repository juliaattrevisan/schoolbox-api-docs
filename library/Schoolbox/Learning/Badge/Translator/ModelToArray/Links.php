<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Translator\ModelToArray;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Schoolbox\Learning\Badge\Model;
use Schoolbox\User;

/**
 * Translator for loading links for the badge.
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
            'learningBadge.awardAsAchievement',
            ['id' => $badge->getId(),]
        );
        $translation['_links']['setAsGoal'] = $this->getRouteIfAuthorized(
            'learningBadge.setAsGoal',
            ['id' => $badge->getId(),]
        );
        $translation['_links']['setAsGoalForSelf'] = $this->getRouteIfAuthorized(
            'learningBadge.setAsGoalForSelf',
            ['id' => $badge->getId(),]
        );

        // Modify/delete routes (not yet implemented)
//        $translation['_links']['modifyForm'] = $this->getRouteIfAuthorized(
//            'learningBadge.getModifyForm',
//            [
//                'id' => $badge->getId(),
//            ]
//        );
//        $translation['_links']['put'] = $this->getRouteIfAuthorized(
//            'learningBadge.putBadge',
//            [
//                'id' => $badge->getId(),
//            ]
//        );
//        $translation['_links']['delete'] = $this->getRouteIfAuthorized(
//            'learningBadge.deleteBadge',
//            [
//                'id' => $badge->getId(),
//            ]
//        );


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
