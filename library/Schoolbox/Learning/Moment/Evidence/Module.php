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
use Alaress\Web\Controller\Base;
use Closure;
use Schoolbox\Discussion\Comment\Mapper as CommentMapper;
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
        $isParentOrUserRule = new Rule\LazyCallback(function () {
            return new Rule\IsUserOrParentOfUser(
                $this->getExternalUser(),
                UserMapper::getInstance(),
                'userId'
            );
        });

        // In addition: if posting a moment for someone else,
        // that someone else must be a student in a year level which learning_moments_enabled is true
        $isTargetUserSelfOrStudentRule = new Rule\Callback(function (array $params, User $user): void {
            $this->authorizeAllUsersAreSelfOrStudent($params, $user);
        });

        // If evidence_parent_create is ON, parents can create on behalf of the student
        $createRule = $this->getConfig()->get('evidence_parent_create') ? $isParentOrUserRule : new Rule\IsUser('userId');

        $viewCreateFormRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
                new Rule\LazyCallback(function () use ($createRule) {
                    return new Rule\Callback(function (array $params, User $user) use ($createRule): void {
                        $this->authorizeAllUsers($params, $user, $createRule);
                    });
                })
            ),
            $isTargetUserSelfOrStudentRule,
        );

        /* Superusers, Staff, Parents of Evidence Recipient and Evidence Recipients
         * can view the detail screen
         */
        $viewEvidenceDetailRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
                $isParentOrUserRule
            )
        );

        /* Superusers, Staff and Evidence Creators can modify or delete an
         * Evidence instance
         */
        $modifyEvidenceRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
                new Rule\IsUser('creatorId')
            )
        );
        $deleteEvidenceRule = $modifyEvidenceRule;


        // Preloaders:
        // convert userIds (a comma-separated list of users) into a user
        // collection
        $viewCreateFormPreloader = Closure::fromCallable([$this, 'preloadUsers']);


        // Routes to register:

        // Redirect to create form, given a set of user IDs in the query string
        $this->register(
            self::METHOD_GET,
            '/user/create',
            'redirectToCreateForm',
            new Rule\IsLoggedIn()
        );

        // Create form for evidence
        $this->register(
            self::METHOD_GET,
            '/user/{userIds}/create',
            'getCreateForm',
            $viewCreateFormRule,
            $viewCreateFormPreloader
        );

        // Post evidence to users
        $this->register(
            self::METHOD_POST,
            '/user/{userIds}/create',
            'postMomentForUsers',
            $viewCreateFormRule,
            $viewCreateFormPreloader
        );

        // Rule for viewing a user's moments or feed:
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
                    new Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
                    $isParentOrUserRule,
                ),
            ),
            Closure::fromCallable([$this, 'preloadUser']),
        );

        $this->register(
            self::METHOD_GET,
            '/tags',
            'getTags',
            new Rule\IsLoggedIn(),
        );

        /* Evidence Detail screen
         */
        $this->register(
            self::METHOD_GET,
            '/{evidenceId}',
            'getEvidenceDetail',
            $viewEvidenceDetailRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        // Evidence modify form
        $this->register(
            self::METHOD_GET,
            '/{evidenceId}/modify',
            'getModifyForm',
            $modifyEvidenceRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        // Evidence modify form
        $this->register(
            self::METHOD_PUT,
            '/{evidenceId}',
            'putEvidence',
            $modifyEvidenceRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        /* Evidence Delete
         */
        $this->register(
            self::METHOD_DELETE,
            '/{evidenceId}',
            'deleteEvidence',
            $deleteEvidenceRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
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
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(CommentMapper::class),
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
     * Preload the requested moment evidence
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     * @throws \InvalidArgumentException
     */
    private function preloadEvidence(array $params): array
    {
        $evidence = $this->getMapperFactory()
             ->get(Mapper::class)
             ->findById($params['evidenceId']);

        return [
            'userId' => $evidence->getRecipient()->getId(),
            'creatorId' => $evidence->getCreator()->getId(),
            'evidence' => $evidence
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

        // It's acceptable for some users in userIds to not be found.
        // However, at least one user must be found for this form to have
        // something to act upon.
        if (count($users) === 0) {
            throw new NotFound();
        }

        return ['users' => $users,];
    }


    /**
     * Authorizes ALL users in the URL against the authorizationRule provided
     *
     * @param array                   $params
     * @param User                    $currentUser
     * @param Rule\IAuthorizationRule $authorizationRule The auth rule used to authorize the user
     */
    private function authorizeAllUsers(
        array $params,
        User $currentUser,
        Rule\IAuthorizationRule $authorizationRule
    ): void {
        // Coalesce to empty list as the preloader may have returned not found,
        // and thus not populated $params['users']
        foreach ($params['users'] ?? [] as $user) {
            $authorizationRule->authorize(['userId' => $user->getId()], $currentUser);
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
            if (!$user->getLearningMomentsEnabled()) {
                throw new Forbidden();
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
