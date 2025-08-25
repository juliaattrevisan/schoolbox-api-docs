<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment;

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
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Resource\Storage;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;
use Schoolbox\Editor\Templates\Mapper as EditorTemplateMapper;
use Schoolbox\Admin\Editor\Templates\Category\Model as EditorTemplatesCategoryModel;

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

        $modifyEvidenceRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
                new Rule\IsUser('creatorId')
            )
        );

        /* Learning Moment Deletion
         *
         * Ability to delete is synonymous with the ability to modify
         */
        $deleteEvidenceRule = $modifyEvidenceRule;

        /* Learning Moment Cohort Report
         *
         * Superusers, Staff with Learning Moment role permission can view/modify cohort report
         */
        $viewCohortReportRule = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRolePermission(Role::PERMISSION_ACCESS_EVIDENCE, Role::STAFF),
            )
        );

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
            '/user/{userId:\d+}',
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
            '/{evidenceId:\d+}',
            'getEvidenceDetail',
            $viewEvidenceDetailRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        // Evidence modify form
        $this->register(
            self::METHOD_GET,
            '/{evidenceId:\d+}/modify',
            'getModifyForm',
            $modifyEvidenceRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        /* Create a whole new evidence instance in the Published state, from nothing previously
         * This is when students are not moderated and spawn a new learning moment directly into
         * the world, skipping the whole moderation workflow.
         */
        $this->register(
            self::METHOD_POST,
            '/user/{userIds}/publish',
            'postPublish',
            $viewCreateFormRule,
            $viewCreateFormPreloader
        );

        /* Create a whole new evidence instance in the Draft state, from nothing previously
         * This seems unique to the Class List "Share a Learning Moment"
         */
        $this->register(
            self::METHOD_POST,
            '/user/{userIds}/draft',
            'postDraft',
            $viewCreateFormRule,
            $viewCreateFormPreloader
        );

        /* Put an evidence into the Draft state as-is;
         * Used from the Activity Feed
         */
        $this->register(
            self::METHOD_PUT,
            '/{evidenceId:\d+}/draft',
            'putDraft',
            $modifyEvidenceRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        /* Create a whole new evidence instance in the Submitted state, from nothing previously
         */
        $this->register(
            self::METHOD_POST,
            '/user/{userIds}/submit',
            'postSubmit',
            $viewCreateFormRule,
            $viewCreateFormPreloader,
        );

        /* Put an existing evidence into the Submitted state as-is;
         * Used from the Activity Feed
         */
        $this->register(
            self::METHOD_PUT,
            '/{evidenceId:\d+}/submit',
            'putSubmit',
            $modifyEvidenceRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        /* Put an evidence into the Published state as-is;
         * Used from the Activity Feed by both students and staffs
         */
        $this->register(
            self::METHOD_PUT,
            '/{evidenceId:\d+}/publish',
            'putPublish',
            $modifyEvidenceRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        // Evidence modify form
        $this->register(
            self::METHOD_PUT,
            '/{evidenceId:\d+}',
            'putEvidence',
            $modifyEvidenceRule,
            Closure::fromCallable([$this, 'preloadEvidence']),
        );

        /* Evidence Delete
         */
        $this->register(
            self::METHOD_DELETE,
            '/{evidenceId:\d+}',
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

        /* Learning Moment Cohort Report payload with applied filters
         */
        $this->register(
            self::METHOD_GET,
            '/cohort/{folderId}/payload',
            'getCohortReportPayload',
            $viewCohortReportRule,
            Closure::fromCallable([$this, 'preloadFolder']),
        );

        /* Learning Moment Cohort Report screen
         */
        $this->register(
            self::METHOD_GET,
            '/cohort/{folderId}',
            'getCohortReport',
            $viewCohortReportRule,
            Closure::fromCallable([$this, 'preloadFolder']),
        );

        /* Learning Moment Cohort Report folder filter
         * Obtains a list of folders for a staff member to filter upon.
         *
         * This might be better served in the \Schoolbox\Resource\Folder module instead of here,
         * but this is the first usage afaict
         */
        $this->register(
            self::METHOD_GET,
            '/folders',
            'getFolderList',
            $viewCohortReportRule,
        );

        /* Bulk Delete a set of learning moments (typically from Cohort Overview)
         */
        $this->register(
            self::METHOD_DELETE,
            '/bulk/delete',
            'deleteBulkDelete',
            $viewCohortReportRule,
        );
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(CommentMapper::class),
            new EditorTemplateHelper(
                $this->getMapperFactory()->get(EditorTemplateMapper::class),
                EditorTemplatesCategoryModel::LEARNING_MOMENTS,
                $this->injector->get('user')
            ),
            $this->getMapperFactory()->get(\Schoolbox\Permission\Access\UserAccessMapper::class),
            new Storage\Translator\ModelToArray(new PrefixGenerator($this->getConfig())),
            $this->getMapperFactory()->get(\Schoolbox\Resource\Folder\Mapper::class)
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
     * Preload the requested folder for the cohort report
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function preloadFolder(array $params): array
    {
        return [
            'folder' => $this->getMapperFactory()
                ->get(\Schoolbox\Resource\Folder\Mapper::class)
                ->findById($params['folderId']),
        ];
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
