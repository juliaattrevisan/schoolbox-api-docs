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
use Alaress\Web\Controller\Base;
use Closure;
use Schoolbox\Filter\Mapper as FilterMapper;
use Schoolbox\Filter\Selection\Mapper as FilterSelectionMapper;
use Schoolbox\Learning\Badge\Authorizer\Rule\IsAwardGoal;
use Schoolbox\Learning\Badge\Authorizer\Rule\IsRecipient;
use Schoolbox\Learning\Badge\Mapper as BadgeMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;
use Schoolbox\Editor\Templates\Mapper as EditorTemplateMapper;
use Schoolbox\Admin\Editor\Templates\Category\Model as EditorTemplatesCategoryModel;

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
                new Rule\HasRolePermission(Role::PERMISSION_CREATE_BADGES, Role::STAFF),
            ),
        );

        /* This is for pages specific to a particular student.
         */
        $viewAwardedBadgesForStudent = new Rule\LazyCallback(function () {
            return new Rule\All(
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
        });

        // Different from above, as not allowing parent
        $postEvidence = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\All(
                        new IsAwardGoal(),
                        new IsRecipient(),
                    )
                ),
            );
        });

        /* This is for pages not specific to a particular student.
         */
        $viewAwardedBadgesForAllStudents = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
        });

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
            $viewAwardedBadgesForStudent,
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

        $this->register(
            self::METHOD_POST,
            '/{awardId}/view',
            'setViewedOn',
            $setGoalForSelfRule,
            Closure::fromCallable([$this, 'preloadAward'])
        );

        /* Update properties about an Awarding, such as its Reason
         */
        $this->register(
            self::METHOD_GET,
            '/{awardId}/update',
            'getAwardUpdate',
            $postEvidence,
            Closure::fromCallable([$this, 'preloadAward'])
        );

        /* Update properties about an Awarding, such as its Reason
         */
        $this->register(
            self::METHOD_POST,
            '/{awardId}/update',
            'postAwardUpdate',
            $postEvidence,
            Closure::fromCallable([$this, 'preloadAward'])
        );

        /* Create new achievement from particular goal badge_user
         */
        $this->register(
            self::METHOD_POST,
            '/{awardId}/achieve',
            'postAwardAchieve',
            $manageBadgesRule,
            Closure::fromCallable([$this, 'preloadAward'])
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
            '/user/{userIds}/award',
            'getAwardWithEvidenceForm',
            $manageBadgesRule,
            $preloadUsers
        );

        $this->register(
            self::METHOD_POST,
            '/user/awardWithEvidence',
            'postAwardWithEvidence',
            $manageBadgesRule
        );

        $this->register(
            self::METHOD_GET,
            '/{awardId}/viewEvidence',
            'getAwardWithEvidence',
            $viewAwardedBadgesForStudent,
            Closure::fromCallable([$this, 'preloadAward'])
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

        $this->register(
            self::METHOD_GET,
            '/list/{folderId}/data',
            'getListByFolderId',
            $viewAwardedBadgesForAllStudents,
            Closure::fromCallable([$this, 'preloadFolder'])
        );

        $this->register(
            self::METHOD_GET,
            '/list/{folderId}',
            'getListPage',
            $viewAwardedBadgesForAllStudents,
            Closure::fromCallable([$this, 'preloadFolder'])
        );
    }


    /**
     * Preload the requested student
     *
     * @param array $params
     * @return array
     * @throws MapperException
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
     * @throws MapperException
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
     * @throws MapperException
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
     * Preload the requested award
     *
     * @param array $params
     * @return array
     */
    private function preloadAward(array $params): array
    {
        if (($params['award'] ?? null) instanceof Model) {
            $award = $params['award'];
        } else {
            try {
                $award = $this->getMapperFactory()
                              ->get(Mapper::class)
                              ->findById($params['awardId']);
            } catch (MapperException $e) {
                throw new \Schoolbox\Application\Exception\NotFound("No award found for id: {$params['awardId']}");
            }
        }
        return [
            'award'  => $award,
            'userId' => $award->getRecipient()->getId(),
        ];
    }


    /**
     * Loads Folder Model instance from route params
     *
     * @param array $params
     * @return array
     * @throws MapperException
     */
    private function preloadFolder(array $params): array
    {
        $folder   = 'folder';
        $folderId = 'folderId';

        if (($params[$folder] ?? null) instanceof \Schoolbox\Resource\Folder\Model) {
            $preload[$folder] = $params[$folder];
        } elseif (!array_key_exists($folderId, $params)) {
            throw new \UnexpectedValueException("No 'folderId' found in route params");
        } else {
            $preload[$folder] = $this->getMapperFactory()
                                     ->get(\Schoolbox\Resource\Folder\Mapper::class)->findById($params[$folderId]);
        }

        return $preload;
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
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(BadgeMapper::class),
            $this->injector->get('filters.factoryFactory'),
            $this->getMapperFactory()->get(FilterMapper::class),
            $this->getMapperFactory()->get(FilterSelectionMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
            new EditorTemplateHelper(
                $this->getMapperFactory()->get(EditorTemplateMapper::class),
                EditorTemplatesCategoryModel::BADGE_EVIDENCE,
                $this->injector->get('user')
            ),
            $this->injector->get('l10n')
        );
    }
}
