<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence;

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

        $viewCreateFormRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\LazyCallback(function () use ($isParentOrUserRule) {
                    return new Rule\Callback(function (array $params, User $user) use ($isParentOrUserRule) {
                        $this->authorizeAllUsers($params, $user, $isParentOrUserRule);
                    });
                })
            ),
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
            '/user/{userId}/create',
            'postMomentForUser',
            $isParentOrUserRule,
            Closure::fromCallable([$this, 'preloadUser']),
        );
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
     * @throws \Schoolbox\Mapper\Exception\Deleted
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
    private function authorizeAllUsers(array $params, User $currentUser, Rule\IAuthorizationRule $isParentOrUserRule): void
    {
        // Coalesce to empty list as the preloader may have returned not found,
        // and thus not populated $params['users']
        foreach ($params['users'] ?? [] as $user) {
            $isParentOrUserRule->authorize(['userId' => $user->getId()], $currentUser);
        }
    }
}
