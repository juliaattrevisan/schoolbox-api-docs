<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge\Award;

use Alaress_Date_Format;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Exception;
use PDOException;
use Schoolbox\Application\WebRequest\RequestUriParser;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
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
     * Constructor
     *
     * @param Mapper                  $mapper
     * @param BadgeMapper             $badgeMapper
     * @param Filter\IFactoryFactory  $filterFactoryFactory
     * @param Filter\Mapper           $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param UserMapper              $userMapper
     */
    public function __construct(
        Mapper $mapper,
        BadgeMapper $badgeMapper,
        Filter\IFactoryFactory $filterFactoryFactory,
        Filter\Mapper $filterMapper,
        Filter\Selection\Mapper $filterSelectionMapper,
        UserMapper $userMapper
    ) {
        $this->badgeMapper           = $badgeMapper;
        $this->mapper                = $mapper;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterMapper          = $filterMapper;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->userMapper            = $userMapper;
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
        $this->response->redirect->to(
            $this->addQueryStringParamsToPath(
                $this->router->generate(
                    'learningBadgeAward.getAwardAsAchievementForm',
                    ['userIds' => $this->request->query->get('userIds')]
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
        $this->response->redirect->to(
            $this->addQueryStringParamsToPath(
                $this->router->generate(
                    'learningBadgeAward.getSetAsGoalForm',
                    ['userIds' => $this->request->query->get('userIds')]
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
        $queryArgs = $this->request->query->getArrayCopy();

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
        $requestUri = RequestUriParser::parseUrlPath($this->request->server->getArrayCopy());
        $returnUrl  = $requestUri . '?' . http_build_query($queryArgs);

        $translator = new User\Translator\Author\ModelToArray();
        $this->response->content->set(
            $this->buildHtmlOutput(
                $template,
                [
                    'settingForSelf' => $settingForSelf,
                    'users'          => $targetUsers->map($translator),
                    'otherUsers'     => $otherUsers->map($translator),
                    'filterPanel'    => $filterPanel,
                    'returnUrl'      => Redirect::encode($returnUrl),
                ],
                $title
            )
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
        $awards = $this->mapper->findRecentForUser($user, $awardStatus, Alaress_Date_Format::now());

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

            /* Some inline hydration due to curriculum nodes no longer being included
             * as part of the hydrateSelect in badge mapper
             */
            $award->getBadge()->setCurriculumNodes($this->badgeMapper->findCurriculumNodes($award->getBadge()));

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
             * findRecentForUser() is listed reverse chronologically so the first
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

        $this->response->content->set($this->buildJsonOutput($stack));
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
        $this->createAwardsFromRequestBody(
            $badge,
            AwardModel::AWARD_STATUS_ACHIEVEMENT
        );
    }


    /**
     * Set a badge as a goal for a set of users in the request body.
     *
     * @api   POST /learning/badge/{badgeId}/goal
     * @since 20.2
     *
     * @param Model $badge The badge to set as a goal.
     * @throws MapperException
     */
    public function setAsGoal(Model $badge): void
    {
        $this->createAwardsFromRequestBody(
            $badge,
            AwardModel::AWARD_STATUS_GOAL
        );
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
    private function createAwardsFromRequestBody(Model $badge, string $status): void
    {
        // Users are in the data.
        // For now: badges may only be awarded to or set as goals for students.
        $data       = json_decode($this->request->content->getRaw(), true);
        $collection = $this->mapper->createCollection();
        $userIds    = array_column($data['users'], 'id');
        $users      = $this->userMapper->findByColumn('id', $userIds);
        $reason     = trim((string)$data['reason']);

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

        foreach ($users as $user) {
            $award = $this->mapper->createModel()
                ->setRecipient($user)
                ->setBadge($badge)
                ->setStatus($status)
                ->setReason($reason)
                ->setAwardedBy($this->getUser())
                ->setAwardedAt(Alaress_Date_Format::now());
            $award = $award->setExpiresAt((new ExpiryHelper())->determineExpiry($award));

            $history = $histories[$user->getId()];

            (new Transactor($history, $this->mapper))->award($award);

            // For now: assume that the current award is being saved as-is
            $collection->add($award);
        }

        // Return what was just created
        $translator = $this->buildBadgeAwardModelToArrayTranslator();
        $this->response->content->set(
            $this->buildJsonOutput($collection->map($translator))
        );
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

        $this->response->content->set(
            $this->buildJsonOutput($translator($award))
        );
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
        $keyword = (string)($this->getRequest()->query['query'] ?? '');

        /* For now, just search for student recipients
         */
        $users = $this->mapper->findRecipients($keyword, $badge, AwardModel::AWARD_STATUS_ACHIEVEMENT, Role::STUDENT);

        $this->response->content->set($this->buildJsonOutput($users));
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
        $keyword = (string)($this->getRequest()->query['query'] ?? '');

        /* For now, just search for student recipients
         */
        $users = $this->mapper->findRecipients($keyword, $badge, AwardModel::AWARD_STATUS_GOAL, Role::STUDENT);

        $this->response->content->set($this->buildJsonOutput($users));
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
        $userIds = $this->getRequest()->post['userIds'] ?? [];

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

            $this->getResponse()->status->setCode(400);
            $this->getResponse()->content->set($this->buildJsonOutput($problem));
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
            || !$award->getViewedOn()->isNull()
        ) {
            return;
        }

        $award->setViewedOn(\Alaress_Date_Format::now());
        $this->mapper->save($award);
    }


    /**
     * Builder for the model-to-array translator for badge awards.
     *
     * @return Translator\ModelToArray
     */
    private function buildBadgeAwardModelToArrayTranslator(): Translator\ModelToArray
    {
        $factory = new Translator\ModelToArray\Factory(
            $this->router,
            $this->authorizer,
            $this->getUser(),
            new PrefixGenerator($this->getConfigLoader()),
        );
        return $factory->build();
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
            $this->request->query->getArrayCopy(),
        );
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningBadgeAward/list.twig',
                [
                    'filterPanel' => $filterPanel,
                    'folder'      => $folder,
                ]
            )->setTab('classes')->setTitle($folder->getName())
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

        $qsFilters      = $this->request->query->get('filters', []);
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

        $this->getResponse()->content->set($this->buildJsonOutput(['data' => $data]));
    }
}
