<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Closure;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Module for the learning moment evidence API and forms.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningMomentEvidence';


    protected function registerRoutes()
    {
        // Permissions:
        // Current user must have permission to post to ALL user IDs provided
        // (they must be a staff member, parent of or the user themselves)
        $isParentOrUserRule = new Rule\IsUserOrParentOfUser(
            $this->getExternalUser(),
            UserMapper::getInstance(),
            'userId'
        );

        // In addition: if posting a moment for someone else,
        // that someone else must be a student
        $isTargetUserSelfOrStudentRule = new Rule\Callback(function (array $params, User $user) {
            $this->authorizeAllUsersAreSelfOrStudent($params, $user);
        });

        $viewCreateFormRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\LazyCallback(function () use ($isParentOrUserRule) {
                    return new Rule\Callback(function (array $params, User $user) use ($isParentOrUserRule) {
                        $this->authorizeAllUsersAreSelfOrParentOfSelf($params, $user, $isParentOrUserRule);
                    });
                })
            ),
            $isTargetUserSelfOrStudentRule,
        );


        // Preloaders:
        // convert userIds (a comma-separated list of users) into a user
        // collection
        $viewCreateFormPreloader = Closure::fromCallable([$this, 'preloadUsers']);


        // Routes to register:
        $this->register(
            self::METHOD_GET,
            '/user/{userIds}/create',
            'getCreateForm',
            $viewCreateFormRule,
            $viewCreateFormPreloader
        );

        $this->register(
            self::METHOD_POST,
            '/user/{userIds}/create',
            'postMomentForUsers',
            $viewCreateFormRule,
            $viewCreateFormPreloader
        );

        // Rule for viewing a user's moments:
        // * Staff and superusers may view anyone's
        // * Students may view their own
        // * Parents may view their own and their kid's
        $this->register(
            self::METHOD_GET,
            '/user/{userId}',
            'getMomentsForUser',
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    $isParentOrUserRule,
                ),
            ),
            Closure::fromCallable([$this, 'preloadUser']),
        );

//        $this->register(
//            self::METHOD_POST,
//            '/user/{userId}/create',
//            'postMomentForUser',
//            $isParentOrUserRule,
//            Closure::fromCallable([$this, 'preloadUser']),
//        );
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'mapper' => $this->getMapperFactory()->getLazy(Mapper::class),
                ]
            )
        );
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
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
     * Preload the requested students.
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
     * Authorizes that for ALL users in the URL that the current user is either:
     * * that user
     * * a parent of that user
     *
     * @param array                   $params
     * @param User                    $currentUser
     * @param Rule\IAuthorizationRule $isParentOrUserRule The auth rule used to authorize the user
     */
    private function authorizeAllUsersAreSelfOrParentOfSelf(
        array $params,
        User $currentUser,
        Rule\IAuthorizationRule $isParentOrUserRule
    ): void {
        // Coalesce to empty list as the preloader may have returned not found,
        // and thus not populated $params['users']
        foreach ($params['users'] ?? [] as $user) {
            $isParentOrUserRule->authorize(['userId' => $user->getId()], $currentUser);
        }
    }


    /**
     * Authorizes that ALL users in the URL are either:
     * * the current user
     * * a student
     *
     * @param array $params
     * @param User  $currentUser
     * @throws Forbidden if any user in URL isn't the current user or a student
     */
    private function authorizeAllUsersAreSelfOrStudent(array $params, User $currentUser): void
    {
        foreach ($params['users'] ?? [] as $user) {
            if (!$user instanceof User) {
                continue;
            }
            if ($currentUser->getId() === $user->getId()) {
                continue;
            }
            if ($user->getRole()->isStudent()) {
                continue;
            }
            throw new Forbidden();
        }
    }
}
