<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Closure;
use Schoolbox\Learning\Badge\Mapper as BadgeMapper;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    protected $basename = 'learningBadgeAward';


    /**
     * @inheritdoc
     */
    public function registerRoutes(): void
    {
        $manageBadgesRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
            ),
        );

        $viewAwardedBadges = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\IsUserOrParentOfUser(
                    $this->getExternalUser(),
                    UserMapper::getInstance(),
                    'userId'
                )
            ),
        );

        $setGoalForSelfRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STUDENT)
        );

        /* Get a list of badges that were awarded to a particular person
         */
        $this->register(
            self::METHOD_GET,
            '/{userId}/awarded/{awardStatus}',
            'getAwardedBadges',
            $viewAwardedBadges,
            Closure::fromCallable([$this, 'preloadUser']),
        );

        /* Get a list of people who have received an Achievement Badge
         */
        $this->register(
            self::METHOD_GET,
            '/{badgeId}/achievement/recipients',
            'getAchievementRecipients',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Withdraw Achievements from awardees
         */
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/achievement/withdraw',
            'postAchievementWithdraw',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Get a list of people who have received a Goal Badge
         */
        $this->register(
            self::METHOD_GET,
            '/{badgeId}/goal/recipients',
            'getGoalRecipients',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Withdraw Goals from awardees
         */
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/goal/withdraw',
            'postGoalWithdraw',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );


        // Set badge as achievement/goal
        $preloadBadge = Closure::fromCallable([$this, 'preloadBadge']);
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/achievement',
            'awardAsAchievement',
            $manageBadgesRule,
            $preloadBadge
        );

        // Only staff may set goals for arbitrary users:
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/goal',
            'setAsGoal',
            $manageBadgesRule,
            $preloadBadge
        );

        // Only students may set goals for themselves
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/setGoalForSelf',
            'setAsGoalForSelf',
            $setGoalForSelfRule,
            $preloadBadge
        );


        // Forms for mass creation of goals/achievements
        // /learning/badge/award/user/{userIds}/{achievement|goal}
        $preloadUsers = Closure::fromCallable([$this, 'preloadUsers']);
        $this->register(
            self::METHOD_GET,
            '/user/achievement',
            'redirectToAwardAsAchievementForm',
            $manageBadgesRule,
        );
        $this->register(
            self::METHOD_GET,
            '/user/{userIds}/achievement',
            'getAwardAsAchievementForm',
            $manageBadgesRule,
            $preloadUsers
        );

        $this->register(
            self::METHOD_GET,
            '/user/goal',
            'redirectToSetAsGoalForm',
            $manageBadgesRule,
        );
        $this->register(
            self::METHOD_GET,
            '/user/{userIds}/goal',
            'getSetAsGoalForm',
            $manageBadgesRule,
            $preloadUsers
        );

        $this->register(
            self::METHOD_GET,
            '/goal',
            'getSetAsGoalForSelfForm',
            $setGoalForSelfRule
        );
    }


    /**
     * Authorizes that the only user in the URL is the current user.
     *
     * @param array $params
     * @param User  $currentUser
     */
    private function authorizeOnlyUserIsSelf(array $params, User $currentUser): void
    {
        $isUserRule = new Rule\IsUser('userId');
        // Coalesce to empty list as the preloader may have returned not found,
        // and thus not populated $params['users']
        foreach ($params['users'] ?? [] as $user) {
            $isUserRule->authorize(['userId' => $user->getId()], $currentUser);
        }
    }


    /**
     * Preload the requested student
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function preloadUser(array $params): array
    {
        return [
            'user' => $this->getMapperFactory()
                           ->get(UserMapper::class)
                           ->findById($params['userId']),
        ];
    }


    /**
     * Preload the requested badge
     *
     * @param array $params
     * @return array
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    private function preloadBadge(array $params): array
    {
        return [
            'badge' => $this->getMapperFactory()
                            ->get(BadgeMapper::class)
                            ->findById($params['badgeId']),
        ];
    }


    /**
     * Preload the requested users.
     *
     * This is the same preloader that the learning moments form uses.
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function preloadUsers(array $params): array
    {
        $userIds = array_unique(explode(',', $params['userIds']));
        $users   = $this->getMapperFactory()
                        ->get(UserMapper::class)
                        ->findByColumn('id', $userIds);

        // It's acceptable for some users in userIds to not be found.
        // However, at least one user must be found for this form to have
        // something to act upon.
        if (count($users) === 0) {
            throw new NotFound();
        }

        return ['users' => $users,];
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'mapper'               => $this->getMapperFactory()->getLazy(Mapper::class),
                    'badgeMapper'          => $this->getMapperFactory()->getLazy(BadgeMapper::class),
                    'filterFactoryFactory' => $injector->lazyGet('filters.factoryFactory'),
                    'userMapper'           => $this->getMapperFactory()->getLazy(UserMapper::class),
                ]
            )
        );
    }
}
