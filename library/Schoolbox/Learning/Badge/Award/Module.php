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
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    protected $basename = 'learningBadgeAward';


    /**
     * @inheritdoc
     */
    public function registerRoutes(): void
    {
        $baseRule = new Rule\All(
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

        /* Get a list of badges that were awarded to a particular person
         */
        $this->register(
            self::METHOD_GET,
            '/{userId}/awarded',
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
            $baseRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Withdraw Achievements from awardees
         */
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/achievement/withdraw',
            'postAchievementWithdraw',
            $baseRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Get a list of people who have received a Goal Badge
         */
        $this->register(
            self::METHOD_GET,
            '/{badgeId}/goal/recipients',
            'getGoalRecipients',
            $baseRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );

        /* Withdraw Goals from awardees
         */
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/goal/withdraw',
            'postGoalWithdraw',
            $baseRule,
            Closure::fromCallable([$this, 'preloadBadge']),
        );


        // Set badge as achievement/goal
        $preloadBadge = Closure::fromCallable([$this, 'preloadBadge']);
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/achievement',
            'awardAsAchievement',
            $baseRule,
            $preloadBadge
        );

        // Only staff may set goals for arbitrary users:
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/goal',
            'setAsGoal',
            $baseRule,
            $preloadBadge
        );

        // any user may set goals for themselves
        $this->register(
            self::METHOD_POST,
            '/{badgeId}/setGoalForSelf',
            'setAsGoalForSelf',
            new Rule\IsLoggedIn(),
            $preloadBadge
        );


        // Forms for mass creation of goals/achievements
        // /learning/badge/user/{userIds}/{achievement|goal}
        $preloadUsers = Closure::fromCallable([$this, 'preloadUsers']);
        $this->register(
            self::METHOD_GET,
            '/user/{userIds}/achievement',
            'getBadgeAwardAsAchievementForm',
            $baseRule,
            $preloadUsers
        );

        $this->register(
            self::METHOD_GET,
            '/user/{userIds}/goal',
            'getBadgeSetAsGoalForm',
            $baseRule,
            $preloadUsers
        );
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

        // Not found if any of the users in the list were not found
        if (count($users) !== count($userIds)) {
            throw new NotFound();
        }

        return ['users' => $users,];
    }


    /**
     * @return \External_UserInterface
     */
    protected function getExternalUser()
    {
        return $this->getInjector()->get('externalUser');
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
