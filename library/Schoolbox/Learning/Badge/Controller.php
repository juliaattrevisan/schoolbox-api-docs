<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Schoolbox\API\Cursor\MapperCursor;
use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Learning\Badge\Award\Model as AwardModel;
use Schoolbox\Learning\Badge\Translator;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Controller for badge requests.
 */
class Controller extends Base
{
    // How many badges show per page by default?
    private const BADGES_PER_PAGE = 40;


    /**
     * The badge mapper.
     */
    private Mapper $mapper;


    /**
     * @var Award\Mapper
     */
    private Award\Mapper $awardMapper;


    /**
     * Constructor
     *
     * @param Mapper       $mapper
     * @param Award\Mapper $awardMapper
     */
    public function __construct(Mapper $mapper, Award\Mapper $awardMapper)
    {
        $this->mapper = $mapper;
        $this->awardMapper = $awardMapper;
    }


    /**
     * Retrieve a list of badges.
     *
     * Depending on request type, this will fetch either a JSON or HTML result:
     * The HTML result will only contain a Vue component, the JSON will contain
     * the actual results.
     *
     * @api   GET /learning/badge/
     * @since 20.2
     */
    public function getBadges(): void
    {
        if ($this->isJsonRequest()) {
            $cursor  = new MapperCursor(
                $this->mapper,
                self::BADGES_PER_PAGE,
                [
                    'name' => $this->mapper::SORT_ORDER_ASC,
                    'id'   => $this->mapper::SORT_ORDER_ASC,
                ]
            );
            $results = $cursor->retrieveResults($this->request->query->get('cursor', null));
            $this->response->content->set(
                $this->buildJsonOutput([
                    'data'     => $results->map($this->buildModelToArrayTranslator()),
                    'metadata' => [
                        'count'  => $cursor->retrieveResultCount(),
                        'cursor' => [
                            'next'    => $cursor->next($results),
                            'current' => $cursor->current($results),
                        ],
                    ],
                ])
            );
            return;
        }

        // Output a Twig template
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/index.twig',
                [],
                'Badges'
            )
        );
    }


    /**
     * Get a list of badges and their counts, awarded to $user
     *
     * @api GET /learning/badge/{userId}/awarded
     *
     * @param User $user
     * @return void
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws MapperException
     * @throws \UnexpectedValueException
     */
    public function getAwardedBadges(User $user): void
    {
        $awards = $this->awardMapper->findRecentForUser($user);

        $stack = [];

        /* Award translator; uses a Badge translator to convert one of
         * the parts into a fully-fledged badge array representation
         */
        $translator = new Award\Translator\ModelToArray(
            new User\Translator\ModelToArray(),
            new Translator\ModelToArray(
                new User\Translator\ModelToArray(),
                new Storage\Translator\ModelToArray(),
                new ScrubAdapter(),
            ),
            new Award\Translator\Links($this->getRouter(), $this->getAuthorizer(), $this->getUser())
        );

        /* Group by category
         */
        foreach ($awards as $award) {

            /* Force string indexes, otherwise the numeric values get inserted in-order
             * which isn't intended here; they are already in a particular order
             */
            $badgeId = "badge-{$award->getBadge()->getId()}";
            $categoryId = "category-{$award->getBadge()->getCategory()->getId()}";

            /* Some inline hydration due to curriculum nodes no longer being included
             * as part of the hydrateSelect in badge mapper
             */
            $award->getBadge()->setCurriculumNodes($this->mapper->findCurriculumNodes($award->getBadge()));

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
     * View the form for creating a badge.
     *
     * @api   GET /learning/badge/create/
     * @since 20.2
     */
    public function getCreateForm(): void
    {
        $attachzone = new GenericZone('image', Storage\Mapper::getInstance());
        $attachzone
            ->setAttachmentsLabel('')
            ->setAttachPrompt('upload a badge')
            ->setMimeFilter('image/*')
            ->setFileVar('image')
            ->setMulti(false)
            ->setThumbnailSize(64)
            ->setModalTitle('Select image');

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/createForm.twig',
                [
                    'attachzone'               => $attachzone->getTwigContext(),
                    'categories'               => Category\Mapper::getInstance()->findAll(),
                    'curriculumTaggingEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
                ],
                'Create Badge'
            )
        );
    }


    /**
     * Create a badge from POST data.
     *
     * @api   POST /learning/badge/
     * @since 20.2
     */
    public function postBadge(): void
    {
        $data       = json_decode(json_encode($this->request->content->get()), true);
        $translator = new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $model      = $translator($data)
            ->setCreatedBy($this->getUser())
            ->setUpdatedBy($this->getUser());

        // Is curriculum tagging disabled?
        // Set an empty collection if so, as that is required to save the badge
        if (!$this->getConfigLoader()->get('curriculum_tagging_enabled')) {
            $model = $model->setCurriculumNodes(\Schoolbox\Curriculum\Node\Mapper::getInstance()->createCollection());
        }

        $this->mapper->save($model);

        // Return the badge just created
        $this->response->content->set(
            $this->buildJsonOutput(
                $this->buildModelToArrayTranslator()($model)
            )
        );
    }


    /**
     * Award a badge as an achievement for a set of users in the request body.
     *
     * @api   POST /learning/badge/{id}/award
     * @since 20.2
     *
     * @param Model $badge The badge to award as an achievement.
     * @throws MapperException
     */
    public function awardBadge(Model $badge): void
    {
        $this->createBadgeAwardsFromRequestBody(
            $badge,
            AwardModel::AWARD_STATUS_ACHIEVEMENT
        );
    }


    /**
     * Set a badge as a goal for a set of users in the request body.
     *
     * @api   POST /learning/badge/{id}/goal
     * @since 20.2
     *
     * @param Model $badge The badge to set as a goal.
     * @throws MapperException
     */
    public function setBadgeAsGoal(Model $badge): void
    {
        $this->createBadgeAwardsFromRequestBody(
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
     * @param Model  $badge  The badge to award.
     * @param string $status The status to award the badge with:
     *                       * `AwardModel::AWARD_STATUS_ACHIEVEMENT` for
     *                         awarding the badge as an achievement
     *                       * `AwardModel::AWARD_STATUS_GOAL` for awarding the
     *                         badge as a goal to be achieved
     * @throws MapperException
     */
    private function createBadgeAwardsFromRequestBody(Model $badge, string $status): void
    {
        // Users are in the data.
        // So far: it's assumed all staff may award or set a badge as a goal to
        // any other user.
        $data       = json_decode($this->request->content->getRaw(), true);
        $collection = $this->awardMapper->createCollection();
        foreach ($data['users'] as $user) {
            $award = $this->awardMapper->createModel()
                // TODO inject user mapper
                ->setRecipient(UserMapper::getInstance()->findById($user['id']))
                ->setBadge($badge)
                ->setStatus($status)
                ->setAwardedBy($this->getUser())
                ->setAwardedAt(Alaress_Date_Format::now());
            $collection->add($award);
        }
        $this->awardMapper->save($collection);

        // Return what was just created
        $translator = new Award\Translator\ModelToArray(
            new User\Translator\ModelToArray(),
            new Translator\ModelToArray(
                new User\Translator\ModelToArray(),
                new Storage\Translator\ModelToArray(),
                new ScrubAdapter()
            )
        );
        $this->response->content->set(
            $this->buildJsonOutput($collection->map($translator))
        );

    }


    public function setBadgeAsGoalForSelf(Model $badge): void
    {

    }


    /**
     * Withdraw an Awarding of $badge to a specific set of users
     *
     * @param Model $badge
     * @throws \PDOException
     */
    public function postWithdraw(Model $badge): void
    {
        $userIds = $this->getRequest()->post['userIds'] ?? [];

        /* Empty user list; nothing to do
         */
        if (!\count($userIds)) {
            return;
        }

        $this->awardMapper->deleteMostRecentlyAwarded($userIds, $badge);
    }


    /**
     * Builder for the model-to-array translator for badges.
     *
     * @return Composite
     */
    private function buildModelToArrayTranslator(): Composite
    {
        return new Composite(
            new Translator\ModelToArray(
                new User\Translator\ModelToArray(),
                new Storage\Translator\ModelToArray(),
                new ScrubAdapter()
            ),
            new Translator\ModelToArray\Links(
                $this->router,
                $this->authorizer,
                $this->getUser()
            )
        );
    }
}
