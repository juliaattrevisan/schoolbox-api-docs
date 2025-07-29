<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress_Date_Format;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Crell\ApiProblem\ApiProblem;
use Exception;
use PDOException;
use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Badge\Award\Model as AwardModel;
use Schoolbox\Learning\Badge\Mapper as BadgeMapper;
use Schoolbox\Learning\Badge\Model;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\SortUsersByName;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use Schoolbox\Learning\Badge\Translator as BadgeTranslator;
use Schoolbox\Resource\Storage;
use Schoolbox\TokenReplacer;
use Schoolbox\Editor\Templates\Helper as EditorTemplateHelper;

use function count;
use function in_array;

/**
 * Controller for badge award requests.
 */
class Controller extends Base
{
    /**
     * The award mapper.
     */
    private Mapper $mapper;


    /**
     * The badge mapper.
     */
    private BadgeMapper $badgeMapper;


    /**
     * Factory for filter factories: used to build each part of the filter.
     */
    private Filter\IFactoryFactory $filterFactoryFactory;

    /**
     * The filter mapper
     */
    private Filter\Mapper $filterMapper;

    /**
     * Filter selection mapper
     */
    private Filter\Selection\Mapper $filterSelectionMapper;

    /**
     * Mapper for users.
     */
    private UserMapper $userMapper;

    /**
     * Helper for editor templates.
     */
    private EditorTemplateHelper $editorTemplateHelper;

    /**
     * Constructor
     *
     * @param Mapper                  $mapper
     * @param BadgeMapper             $badgeMapper
     * @param Filter\IFactoryFactory  $filterFactoryFactory
     * @param Filter\Mapper           $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param UserMapper              $userMapper
     * @param EditorTemplateHelper    $editorTemplateHelper
     */
    public function __construct(
        Mapper $mapper,
        BadgeMapper $badgeMapper,
        Filter\IFactoryFactory $filterFactoryFactory,
        Filter\Mapper $filterMapper,
        Filter\Selection\Mapper $filterSelectionMapper,
        UserMapper $userMapper,
        EditorTemplateHelper $editorTemplateHelper,
        private RosettaStone $l10n,
    ) {
        $this->badgeMapper           = $badgeMapper;
        $this->mapper                = $mapper;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterMapper          = $filterMapper;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->userMapper            = $userMapper;
        $this->editorTemplateHelper  = $editorTemplateHelper;
    }


    /**
     * Redirect to the user achievement form.
     *
     * This takes a list of user IDs in the 'userId' query string parameter.
     *
     * @api GET /learning/badge/award/user/achievement
     * @since 20.2
     */
    public function redirectToAwardAsAchievementForm(): void
    {
        $this->response = $this->redirectFound(
            $this->addQueryStringParamsToPath(
                $this->routeGenerator->generate(
                    'learningBadgeAward.getAwardAsAchievementForm',
                    ['userIds' => $this->requestQuery->get('userIds')]
                ),
                [Redirect::RETURN_URL]
            )
        );
    }


    /**
     * Show a form for awarding a badge as an achievement to a set of users.
     *
     * @api GET /learning/badge/award/user/{userIds}/achievement
     * @since 20.2
     *
     * @param GenericCollection $users
     */
    public function getAwardAsAchievementForm(GenericCollection $users): void
    {
        $this->getAwardBadgeForm(
            $users,
            '@learningBadgeAward/awardAsAchievementForm.twig',
            'Award Achievements'
        );
    }


    /**
     * Redirect to the user goal form.
     *
     * This takes a list of user IDs in the 'userId' query string parameter.
     *
     * @api GET /learning/badge/award/user/goal
     * @since 20.2
     */
    public function redirectToSetAsGoalForm(): void
    {
        $this->response = $this->redirectFound(
            $this->addQueryStringParamsToPath(
                $this->routeGenerator->generate(
                    'learningBadgeAward.getSetAsGoalForm',
                    ['userIds' => $this->requestQuery->get('userIds')]
                ),
                [Redirect::RETURN_URL]
            )
        );
    }


    /**
     * Show a form for setting a goal for a set of users.
     *
     * @api GET /learning/badge/award/user/{userIds}/goal
     * @since 20.2
     *
     * @param GenericCollection $users
     */
    public function getSetAsGoalForm(GenericCollection $users): void
    {
        $this->getAwardBadgeForm(
            $users,
            '@learningBadgeAward/setAsGoalForm.twig',
            'Set Goals'
        );
    }


    /**
     * Show a form for setting a goal for yourself.
     *
     * @api GET /learning/badge/award/goal
     * @since 20.2
     */
    public function getSetAsGoalForSelfForm(): void
    {
        $this->getAwardBadgeForm(
            $this->userMapper->createCollection()->add($this->getUser()),
            '@learningBadgeAward/setAsGoalForSelfForm.twig',
            'Set Goals'
        );
    }


    /**
     * Show a form for awarding a badge as an achievement or goal to a set of
     * users.
     *
     * @param GenericCollection|User[] $targetUsers
     * @param string                   $template The template to display
     * @param string                   $title    The title of this page
     */
    private function getAwardBadgeForm(GenericCollection $targetUsers, string $template, string $title): void
    {
        $queryArgs = $this->requestQuery->getArrayCopy();

        // In the year level filter, select selected students' year levels and None by default
        $yearLevelFilterDefinition = Filter\Filters\Badge\YearLevel\Definition::class;
        $filters                   = $this->filterMapper->findForEntity(BadgeMapper::class);
        foreach ($filters as $filter) {
            if ($yearLevelFilterDefinition !== $filter->getClass()) {
                continue;
            }

            /* For some reason this is initialising as an empty string, not an array
             * You can't use it in an array context unless you init it first
             */
            if (isset($queryArgs[$filter->getId()]) && ('' === $queryArgs[$filter->getId()])) {
                $queryArgs[$filter->getId()] = [];
            }

            foreach ($targetUsers as $targetUser) {
                if (!$targetUser->getYear() || isset($queryArgs[$filter->getId()][$targetUser->getYear()->getId()])) {
                    continue;
                }

                $queryArgs[$filter->getId()][$targetUser->getYear()->getId()] = 1;
            }

            // select None
            $queryArgs[$filter->getId()][Filter\Filters\Badge\YearLevel\Definition::KEY_NONE] = 1;
        }

        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            Filter\Page\Model::PAGE_BADGE_LIST,
            null,
            $this->getUser(),
            [],
            $queryArgs,
        );

        // Segment users into users which are and are not this user
        $otherUsers     = $targetUsers->emptyClone();
        $thisUserId     = $this->getUser()->getId();
        $settingForSelf = false;
        foreach ($targetUsers as $targetUser) {
            if ($targetUser->getId() === $thisUserId) {
                $settingForSelf = true;
            } else {
                $otherUsers->add($targetUser);
            }
        }

        // requires filter params and returnUrl
        $requestUri = RequestUriParser::parseUrlPath($this->request->getServerParams());
        $returnUrl  = $requestUri . '?' . http_build_query($queryArgs);

        $translator = new User\Translator\Author\ModelToArray();
        $this->response = $this->responseFromTemplate(
            $template,
            [
                'settingForSelf' => $settingForSelf,
                'users'          => $targetUsers->map($translator),
                'otherUsers'     => $otherUsers->map($translator),
                'filterPanel'    => $filterPanel,
                'returnUrl'      => Redirect::encode($returnUrl),
            ],
            $title
        );
    }


    /**
     * Display form to award multiple students with multiple badges
     *
     * @param GenericCollection $users
     * @return void
     * @api   GET /learning/badge/award/user/{userIds}/award
     * @since 22.1
     */
    public function getAwardWithEvidenceForm(GenericCollection $users): void
    {
        $badgeIds = $this->requestQuery->get('badges') ? array_unique(array_map('intval', explode(',', $this->requestQuery->get('badges')))) : [];
        $badges   = $this->badgeMapper->findByColumn('id', $badgeIds);
        if (empty($badgeIds) || !$badges->count()) {
            $this->sendError('No badge provided');
            return;
        }

        $returnUrl = $this->requestQuery->get(Redirect::RETURN_URL) ??
            Redirect::encode($this->routeGenerator->generate('learningBadge.getBadgeListPage'));
        $cancelUrl       = Redirect::decode($returnUrl);
        $badgeTranslator = $this->buildBadgeModelToArrayTranslator();
        $userTranslator  = new User\Translator\Author\ModelToArray();

        $this->response = $this->responseFromTemplate(
            '@learningBadgeAward/awardWithEvidenceForm.twig',
            [
                'isGoal'        => false,
                'badges'        => $badges->map($badgeTranslator),
                'badgeIds'      => implode(',', $badges->getPropertyValues('id')),
                'targetUsers'   => $users->map($userTranslator),
                'targetUserIds' => implode(',', $users->getPropertyValues('id')),
                'returnUrl'     => $returnUrl,
                'cancelUrl'     => $cancelUrl,
                'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
            ],
            'Award Achievement'
        );
    }

    /**
     * Bulk award users with badges and reason
     * @return void
     * @throws MapperException
     * @throws \Alaress\Web\Controller\Exception
     * @api   POST /learning/badge/award/user/awardWithEvidence
     * @since 22.1
     */
    public function postAwardWithEvidence(): void
    {
        $targetUserIds = $this->requestPost->get('targetUserIds') ? array_map('intval', explode(',', $this->requestPost->get('targetUserIds'))) : [];
        $targetUsers   = $this->userMapper->findByColumn('id', $targetUserIds);
        if (empty($targetUserIds) || !$targetUsers->count()) {
            $this->sendError('No target user provided');
            return;
        }

        $reason   = trim($this->requestPost->get('reason'));
        $badgeIds = $this->requestPost->get('badgeIds') ? array_map('intval', explode(',', $this->requestPost->get('badgeIds'))) : [];
        $badges   = $this->badgeMapper->findByColumn('id', $badgeIds);
        if (empty($badgeIds) || !$badges->count()) {
            $this->sendError('No badge provided');
            return;
        }
        foreach ($badges as $badge) {
            $this->createAwardsFromRequestBody($badge, AwardModel::AWARD_STATUS_ACHIEVEMENT, $targetUsers, $reason);
        }
        $returnUrl      = $this->requestQuery->get(Redirect::RETURN_URL);
        $this->response = $this->redirectAfterPost($returnUrl ? Redirect::decode($returnUrl) : $this->getSession()->getHistory());
    }

    /**
     * Show award detail with evidence
     * @param \Schoolbox\Learning\Badge\Award\Model $award
     * @return void
     * @throws RouteNotFound
     * @since 22.1
     * @api   GET /learning/badge/award/{awardId}/viewEvidence
     */
    public function getAwardWithEvidence(AwardModel $award): void
    {
        $badges          = $this->badgeMapper->createCollection()->add($award->getBadge());
        $badgeTranslator = $this->buildBadgeModelToArrayTranslator();
        $returnUrl       = $this->routeGenerator->generate('learningBadgeAward.getAwardWithEvidence', [
            'awardId' => $award->getId(),
            'award'   => $award,
        ]);
        $cancelUrl       = $this->requestQuery->get(Redirect::RETURN_URL)
            ? Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL))
            : $this->routeGenerator->generate('searchUser.getProfile', [
                'userId' => $award->getRecipient()->getId(),
                'user'   => $award->getRecipient(),
            ]);

        $this->response = $this->responseFromTemplate(
            '@learningBadgeAward/awardWithEvidence.twig',
            [
                'badges'    => $badges->map($badgeTranslator),
                'award'     => $award,
                'returnUrl' => Redirect::encode($returnUrl),
                'cancelUrl' => $cancelUrl,
            ],
            'View Evidence'
        );
    }

    /**
     * Get a list of badges and their counts, awarded to $user
     *
     * @api GET /learning/badge/award/{userId}/awarded
     * @param User   $user
     * @param string $awardStatus
     * @return void
     * @throws MapperException
     *
     */
    public function getAwardedBadges(User $user, string $awardStatus): void
    {
        $awards = $this->mapper->findForUser($user, $awardStatus, Alaress_Date_Format::now());

        $stack = [];

        /* Award translator; uses a Badge translator to convert one of
         * the parts into a fully-fledged badge array representation
         */
        $translator = $this->buildBadgeAwardModelToArrayTranslator();

        /* Group by category
         */
        /** @var AwardModel $award */
        foreach ($awards as $award) {
            /* Force string indexes, otherwise the numeric values get inserted in-order
             * which isn't intended here; they are already in a particular order
             */
            $badgeId = $award->getBadgeIndex();
            $categoryId = "category-{$award->getBadge()->getCategory()->getId()}";

            $badge = $award->getBadge();

            if ($badge->isDeleted()) {
                // Skip awards on deleted badges.
                continue;
            }

            /* Some inline hydration due to curriculum nodes no longer being included
             * as part of the hydrateSelect in badge mapper
             */
            $badge->setCurriculumNodes($this->badgeMapper->findCurriculumNodes($award->getBadge()));

            /* Convert to array format
             */
            $badge = $translator($award);

            /* Init stack
             *
             * Remember one copy of the category
             */
            if (!isset($stack['categories'][$categoryId])) {
                $stack['categories'][$categoryId] = $badge['badge']['category'];
                $stack['categories'][$categoryId]['awards'] = [];
            }

            /* Init badge count
             *
             * findForUser() is listed reverse chronologically so the first
             * in the list is the most recently awarded
             */
            if (!isset($stack['categories'][$categoryId]['awards'][$badgeId])) {
                /* This is a set-based aggregation, not an individual model property
                 */
                $badge['count'] = 0;

                /* Stick it into the stack
                 */
                $stack['categories'][$categoryId]['awards'][$badgeId] = $badge;
            }

            /* Collapse duplicate badge ids with a count
             */
            $stack['categories'][$categoryId]['awards'][$badgeId]['count']++;
        }

        $this->response = $this->responseAsJson($stack);
    }


    /**
     * Award a badge as an achievement for a set of users in the request body.
     *
     * @api   POST /learning/badge/award/{badgeId}/achievement
     * @since 20.2
     *
     * @param Model $badge The badge to award as an achievement.
     * @throws MapperException
     */
    public function awardAsAchievement(Model $badge): void
    {
        $this->createAwards($badge, AwardModel::AWARD_STATUS_ACHIEVEMENT);
    }


    /**
     * Set a badge as a goal for a set of users in the request body.
     *
     * @param Model $badge The badge to set as a goal.
     * @throws MapperException
     * @api   POST /learning/badge/{badgeId}/goal
     * @since 20.2
     *
     */
    public function setAsGoal(Model $badge): void
    {
        $this->createAwards($badge, AwardModel::AWARD_STATUS_GOAL);
    }


    /**
     * @param Model  $badge
     * @param string $awardStatus
     * @return void
     * @throws MapperException
     */
    private function createAwards(Model $badge, string $awardStatus): void
    {
        $data    = $this->request->getParsedBody();
        $userIds = array_column($data['users'] ?? [], 'id');
        if (empty($userIds)) {
            $this->sendJsonError("No users provided to set $awardStatus", 400);
            return;
        }

        // Users are in the data.
        // For now: badges may only be awarded to or set as goals for students.
        $users  = $this->userMapper->findByColumn('id', $userIds);
        $reason = trim((string)($data['reason'] ?? ''));

        $collection = $this->createAwardsFromRequestBody(
            $badge,
            $awardStatus,
            $users,
            $reason
        );

        // Return what was just created
        $translator     = $this->buildBadgeAwardModelToArrayTranslator();
        $this->response = $this->responseAsJson($collection->map($translator));
    }


    /**
     * Creates badge awards from the request body.
     *
     * Users to award to will be in the "users" key, and each user will be
     * identified by the "id" key of each item in "users".
     *
     * @param Model  $badge    The badge to award.
     * @param string $status   The status to award the badge with:
     *                         * `AwardModel::AWARD_STATUS_ACHIEVEMENT` for
     *                         awarding the badge as an achievement
     *                         * `AwardModel::AWARD_STATUS_GOAL` for awarding the
     *                         badge as a goal to be achieved
     * @throws MapperException if for some reason a query fails
     * @throws Exception       if attempting to award to a non-student
     */
    private function createAwardsFromRequestBody(Model $badge, string $status, GenericCollection $users, string $reason): GenericCollection
    {
        $collection = $this->mapper->createCollection();
        // Are any of those users not students? Error out
        foreach ($users as $user) {
            if (!$user->getRole()->isStudent()) {
                throw $this->generateException(
                    ErrorMapper::ERROR_BADGE_AWARD_TO_NON_STUDENT_USER,
                    null,
                    '',
                    403
                );
            }
        }

        /* Need a badge history for a particular user to decide how goals
         * and achievements are persisted
         *
         * This section can be optimised further in any number of ways
         */
        $userIds = $users->getPropertyValues('id');
        $histories = $this->mapper->findHistories($userIds, $badge);
        $tokenReplacer = new TokenReplacer\Student(new TokenReplacer\Simple());
        $now = Alaress_Date_Format::now();

        foreach ($users as $user) {
            $award = $this->mapper->createModel()
                ->setRecipient($user)
                ->setBadge($badge)
                ->setStatus($status)
                ->setReason($tokenReplacer->replace($reason, $user))
                ->setReasonedAt($now)
                ->setReasonedBy($this->getUser())
                ->setAwardedBy($this->getUser())
                ->setAwardedAt($now);
            $award = $award->setExpiresAt((new ExpiryHelper())->determineExpiry($award));

            $history = $histories[$user->getId()];

            (new Transactor($history, $this->mapper))->award($award);

            // For now: assume that the current award is being saved as-is
            $collection->add($award);
        }
        return $collection;
    }


    /**
     * Set a badge as a goal for the current user.
     *
     * @api   POST /learning/badge/award/{badgeId}/achievement
     * @since 20.2
     * @param Model $badge
     * @throws MapperException if somehow the save fails
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function setAsGoalForSelf(Model $badge): void
    {
        $histories = $this->mapper->findHistories([$this->getUser()->getId()], $badge);
        $history = $histories[$this->getUser()->getId()];

        $award = $this->mapper->createModel()
            ->setRecipient($this->getUser())
            ->setBadge($badge)
            ->setStatus(AwardModel::AWARD_STATUS_GOAL)
            ->setAwardedBy($this->getUser())
            ->setAwardedAt(Alaress_Date_Format::now());
        $award = $award->setExpiresAt((new ExpiryHelper())->determineExpiry($award));

        (new Transactor($history, $this->mapper))->award($award);

        $translator = $this->buildBadgeAwardModelToArrayTranslator();

        $this->response = $this->responseAsJson($translator($award));
    }


    /**
     * Find a list of guys who received $badge as an Achievement
     *
     * @api   GET /learning/badge/award/{badgeId}/achievement/recipients
     * @since 20.2
     *
     * @param Model $badge
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws PDOException
     */
    public function getAchievementRecipients(Model $badge): void
    {
        $keyword = (string)($this->requestQuery['query'] ?? '');

        /* For now, just search for student recipients
         */
        $yearPrefix = $this->l10n->translate('year', false, true);
        $users = $this->mapper->findRecipients($keyword, $badge, AwardModel::AWARD_STATUS_ACHIEVEMENT, Role::STUDENT, $yearPrefix);

        $this->response = $this->responseAsJson($users);
    }


    /**
     * Find a list of guys who set $badge as a Goal
     *
     * @api   GET /learning/badge/award/{badgeId}/goal/recipients
     * @since 20.2
     *
     * @param Model $badge
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws PDOException
     */
    public function getGoalRecipients(Model $badge): void
    {
        $keyword = (string)($this->requestQuery['query'] ?? '');

        /* For now, just search for student recipients
         */
        $yearPrefix = $this->l10n->translate('year', false, true);
        $users = $this->mapper->findRecipients($keyword, $badge, AwardModel::AWARD_STATUS_GOAL, Role::STUDENT, $yearPrefix);

        $this->response = $this->responseAsJson($users);
    }


    /**
     * Withdraw an achievement that was set for a set of users
     *
     * @api   POST /learning/badge/award/{badgeId}/achievement/withdraw
     * @since 20.2
     *
     * @param Model $badge
     * @throws PDOException
     */
    public function postAchievementWithdraw(Model $badge): void
    {
        $this->postWithdraw($badge, AwardModel::AWARD_STATUS_ACHIEVEMENT);
    }


    /**
     * Withdraw a goal that was set for a set of users
     *
     * @api   POST /learning/badge/award/{badgeId}/goal/withdraw
     * @since 20.2
     *
     * @param Model $badge
     * @throws PDOException
     */
    public function postGoalWithdraw(Model $badge): void
    {
        $this->postWithdraw($badge, AwardModel::AWARD_STATUS_GOAL);
    }


    /**
     * Withdraw an Awarding of $badge to a specific set of users
     *
     * @param Model  $badge
     * @param string $status
     * @throws PDOException
     */
    private function postWithdraw(Model $badge, string $status): void
    {
        $userIds = $this->requestPost['userIds'] ?? [];

        /* Empty user list; nothing to do
         */
        if (!count($userIds)) {
            return;
        }

        /* Must be a valid status
         */
        if (!in_array($status, [AwardModel::AWARD_STATUS_ACHIEVEMENT, AwardModel::AWARD_STATUS_GOAL], true)) {
            return;
        }

        try {
            $this->mapper->deleteMostRecentlyAwarded($userIds, $badge, $status);
        } catch (\RuntimeException $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(400)
                ->asArray();

            $this->response = $this->responseAsJson($problem)->withStatus(400);
        }
    }


    /**
     * Update view date, if this is the first time for this user to view this award.
     *
     * @api   POST /learning/badge/award/{awardId}/view
     * @since 21.1
     * @param AwardModel $award
     * @throws MapperException if somehow the save fails
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function setViewedOn(AwardModel $award): void
    {
        // Set view on, only the viewer is the recipient and viewed on has not been set
        if (
            $this->getUser()->getId() !== $award->getRecipient()->getId()
            || $award->getViewedOn()
        ) {
            return;
        }

        $award->setViewedOn(\Alaress_Date_Format::now());
        $this->mapper->save($award);
    }


    /**
     * For the $award given, update its properties
     *
     * @param \Schoolbox\Learning\Badge\Award\Model $award
     * @return void
     * @throws RouteNotFound
     * @api GET /learning/badge/award/{awardId}/update
     */
    public function getAwardUpdate(AwardModel $award): void
    {
        $badges          = $this->badgeMapper->createCollection()->add($award->getBadge());
        $badgeTranslator = $this->buildBadgeModelToArrayTranslator();

        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
        } elseif ($this->getAuthorizer()->isPathAuthorizedForUser('learningBadgeAward.getAwardWithEvidence', ['awardId' => $award->getId()])) {
            $returnUrl = Redirect::encode(
                $this->routeGenerator->generate('learningBadgeAward.getAwardWithEvidence', [
                    'award'   => $award,
                    'awardId' => $award->getId(),
                ])
            );
        } else {
            $returnUrl = Redirect::encode(
                $this->routeGenerator->generate('searchUser.getProfile', [
                    'user'   => $award->getRecipient(),
                    'userId' => $award->getRecipient()->getId(),
                ])
            );
        }

        $this->response = $this->responseFromTemplate(
            '@learningBadgeAward/awardUpdate.twig',
            [
                'badges'    => $badges->map($badgeTranslator),
                'award'     => $award,
                'returnUrl' => $returnUrl,
                'cancelUrl' => Redirect::decode($returnUrl),
                'editorTemplates' => $this->editorTemplateHelper->getTemplatesForCategory(),
            ],
            'Edit award'
        );
    }


    /**
     * For the $award given, update its properties
     *
     * @param AwardModel $award
     * @return void
     * @throws MapperException
     * @throws PDOException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @api POST /learning/badge/award/{awardId}/update
     */
    public function postAwardUpdate(AwardModel $award): void
    {
        // hydrate student for token replacer
        $student       = $this->userMapper->findById($award->getRecipient()->getId());
        $tokenReplacer = new TokenReplacer\Student(new TokenReplacer\Simple());
        $reason        = trim($this->requestPost->get('reason') ?? '');
        $award->setReason($tokenReplacer->replace($reason, $student))
            ->setReasonedBy($this->getUser())
            ->setReasonedAt(Alaress_Date_Format::now());

        $this->mapper->save($award);

        /* Protect the user from themselves.
         * If there is no redirect link, then just redirect back to the students profile (this shouldn't happen...)
         * else redirect to back to where you came from
         * this redirect is created in the vue form component.
         */
        $redirectUrl = $this->routeGenerator->generate('searchUser.getProfile', [
            'userId' => $award->getRecipient()->getId(),
            'user'   => $award->getRecipient(),
        ]);
        if (null !== $this->requestQuery->get(Redirect::RETURN_URL)) {
            $redirectUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        }
        $this->response = $this->redirectFound($redirectUrl);
    }


    /**
     * Create a new achievement award from the goal award given
     *
     * @param AwardModel $award
     * @return void
     * @throws MapperException
     * @throws RouteNotFound
     * @api   POST /learning/badge/award/{awardId}/achieve
     * @since 22.1
     */
    public function postAwardAchieve(AwardModel $award): void
    {
        if ($award->isAchievement()) {
            $this->sendJsonError("Award {$award->getId()} is already achieved", 400);
            return;
        }

        // create new achievement award from award
        $achievement = $this->mapper->createModel()
            ->setRecipient($award->getRecipient())
            ->setBadge($award->getBadge())
            ->setReason($award->getReason())
            ->setReasonedBy($award->getReasonedBy())
            ->setReasonedAt($award->getReasonedAt())
            ->setStatus(AwardModel::AWARD_STATUS_ACHIEVEMENT)
            ->setAwardedBy($this->getUser())
            ->setAwardedAt(Alaress_Date_Format::now());
        $achievement = $achievement->setExpiresAt((new ExpiryHelper())->determineExpiry($achievement));
        $this->mapper->save($achievement);

        // set the achievement as award_id
        $award->setAward($achievement);
        $this->mapper->save($award);

        $translator      = $this->buildBadgeAwardModelToArrayTranslator();
        $data['viewUrl'] = $this->routeGenerator->generate('learningBadgeAward.getAwardWithEvidence', [
            'award'   => $achievement,
            'awardId' => $achievement->getId(),
        ]);
        $this->response = $this->responseAsJson(array_merge($translator($achievement), $data))->withStatus(201);
    }


    /**
     * Builder for the model-to-array translator for badge awards.
     *
     * @return Translator\ModelToArray
     */
    private function buildBadgeAwardModelToArrayTranslator(): Translator\ModelToArray
    {
        $factory = new Translator\ModelToArray\Factory(
            $this->routeGenerator,
            $this->authorizer,
            $this->getUser(),
            new PrefixGenerator($this->getConfigLoader()),
        );
        return $factory->build();
    }


    /**
     * Builder for the model-to-array translator for badges.
     *
     * @return Composite
     */
    private function buildBadgeModelToArrayTranslator(): Composite
    {
        $prefixGenerator = new PrefixGenerator($this->getConfigLoader());
        return new Composite(
            new BadgeTranslator\ModelToArray(
                new User\Translator\Author\ModelToArray(),
                new Storage\Translator\ModelToArray($prefixGenerator),
                new ScrubAdapter(),
                new BadgeTranslator\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser(), $prefixGenerator)
            ),
            new BadgeTranslator\ModelToArray\Links(
                $this->routeGenerator,
                $this->authorizer,
                $this->getUser()
            )
        );
    }

    /**
     * List students with awarded badges
     *
     * @param Folder $folder
     * @since 21.1
     * @api   GET /learning/badge/award/list/{folderId}
     */
    public function getListPage(Folder $folder): void
    {
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            Filter\Page\Model::PAGE_AWARD_LIST,
            null,
            $this->getUser(),
            [],
            $this->requestQuery->getArrayCopy(),
        );
        $this->response = $this->responseFromTemplate(
            '@learningBadgeAward/list.twig',
            [
                'filterPanel' => $filterPanel,
                'folder'      => $folder,
            ],
            $folder->getName(),
            'classes'
        );
    }


    /**
     * Get students in the folder with awards
     *
     * @param Folder $folder
     * @throws MapperException
     * @api   GET /learning/badge/award/list/{folderId}/data
     * @since 21.1
     */
    public function getListByFolderId(Folder $folder): void
    {
        // Process filters
        $page = Filter\Page\Model::PAGE_AWARD_LIST;

        $qsFilters      = $this->requestQuery->get('filters', []);
        $codec          = new Filter\Codec($this->filterMapper, $this->filterSelectionMapper);
        $user           = $this->getUser();
        $userSelections = $codec->convertRequestToSelections($user, $page, $qsFilters);
        $filters        = $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $user, $page);

        /** @var Filter\Filters\Award\Finder $finder */
        $finder = Filter\Filters\Award\Finder::getInstance($this->filterMapper, $this->mapper);

        // Save selected filters
        $this->filterSelectionMapper->updateSelections($user, $page, $userSelections);

        // Get students
        $userAccessMapper = UserAccessMapper::getInstance();
        $students         = $userAccessMapper
            ->findUsersInCategory($folder, Role::STUDENT)
            ->sortByCallback((new SortUsersByName())->getCallable(), true);

        $awards = $finder->findListForStudentsByFilters($students->getPropertyValues('id'), $filters);

        // Process data
        $studentAwards = [];
        $translator    = $this->buildBadgeAwardModelToArrayTranslator();
        /** @var \Schoolbox\Learning\Badge\Award\Model $award */
        foreach ($awards as $award) {
            $studentAwards[$award->getRecipient()->getId()][$award->getBadgeIndex()] ??= $translator($award);

            $studentAwards[$award->getRecipient()->getId()][$award->getBadgeIndex()]['count'] ??= 0;
            $studentAwards[$award->getRecipient()->getId()][$award->getBadgeIndex()]['count']++;
        }

        $data = [];
        foreach ($students as $student) {
            $data[] = [
                'id'        => $student->getId(),
                'fullName'  => $student->getFullName(),
                'firstName' => $student->getFirstname(),
                'lastName'  => $student->getLastname(),
                'role'      => $student->getPreferredTitle(),
                'badges'    => $studentAwards[$student->getId()] ?? null,
            ];
        }

        $this->response = $this->responseAsJson(['data' => $data]);
    }
}
