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
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\GenericCollection;
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
     * Factory for filter factories: used to build each part of the filter.
     */
    private Filter\IFactoryFactory $filterFactoryFactory;


    /**
     * Mapper for filters.
     */
    private Filter\Mapper $filterMapper;


    /**
     * Mapper for filter selections.
     */
    private Filter\Selection\Mapper $filterSelectionMapper;


    /**
     * Mapper for storage objects.
     */
    private Storage\Mapper $storageMapper;


    /**
     * Mapper for users.
     */
    private UserMapper $userMapper;


    /**
     * Mapper for badge categories.
     */
    private Category\Mapper $categoryMapper;


    /**
     * Mapper for curriculum nodes.
     */
    private CurriculumNodeMapper $curriculumNodeMapper;


    /**
     * Constructor
     *
     * @param Mapper                  $mapper
     * @param Award\Mapper            $awardMapper
     * @param Filter\IFactoryFactory  $filterFactoryFactory
     * @param Filter\Mapper           $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param Storage\Mapper          $storageMapper
     * @param UserMapper              $userMapper
     * @param Category\Mapper         $categoryMapper
     * @param CurriculumNodeMapper    $curriculumNodeMapper
     */
    public function __construct(
        Mapper $mapper,
        Award\Mapper $awardMapper,
        Filter\IFactoryFactory $filterFactoryFactory,
        Filter\Mapper $filterMapper,
        Filter\Selection\Mapper $filterSelectionMapper,
        Storage\Mapper $storageMapper,
        UserMapper $userMapper,
        Category\Mapper $categoryMapper,
        CurriculumNodeMapper $curriculumNodeMapper
    ) {
        $this->mapper                = $mapper;
        $this->awardMapper           = $awardMapper;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterMapper          = $filterMapper;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->storageMapper         = $storageMapper;
        $this->userMapper            = $userMapper;
        $this->categoryMapper        = $categoryMapper;
        $this->curriculumNodeMapper  = $curriculumNodeMapper;
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
        $page = Filter\Page\Model::PAGE_BADGE_LIST;
        if ($this->isJsonRequest()) {
            // Boilerplate for getting a set of badges which match filter
            $qsFilters      = $this->request->query->get('filters', []);
            $codec          = new Codec($this->filterMapper, $this->filterSelectionMapper);
            $user           = $this->getUser();
            $userSelections = $codec->convertRequestToSelections($user, $page, $qsFilters);
            $filters        = $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $user, $page);
            $idFinder       = Filter\Filters\Badge\IdFinder::getInstance($this->filterMapper, $this->mapper);
            $badgeIds       = $idFinder->findIdsByFilters($filters);


            // Save selected filters
            $this->filterSelectionMapper->updateSelections($user, $page, $userSelections);


            // Retrieve badges which match the filter
            if (count($badgeIds) > 0) {
                $cursor  = new MapperCursor(
                    $this->mapper,
                    self::BADGES_PER_PAGE,
                    [
                        'name' => $this->mapper::SORT_ORDER_ASC,
                        'id'   => $this->mapper::SORT_ORDER_ASC,
                    ],
                    $this->mapper->getPredicateBuilder()->in('id', $badgeIds)
                );
                $results = $cursor->retrieveResults($this->request->query->get('cursor'));
                $data    = $results->map($this->buildModelToArrayTranslator());
                $count   = $cursor->retrieveResultCount();
                $next    = $cursor->next($results);
                $current = $cursor->current($results);
            } else {
                $data    = [];
                $count   = 0;
                $next    = null;
                $current = null;
            }
            $this->response->content->set(
                $this->buildJsonOutput([
                    'data'     => $data,
                    'metadata' => [
                        'count'  => $count,
                        'cursor' => [
                            'next'    => $next,
                            'current' => $current,
                        ],
                    ],
                ])
            );
            return;
        }

        // Output a Twig template
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            $page,
            null,
            $this->getUser(),
            [],
            $this->request->query->getArrayCopy(),
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/index.twig',
                ['filterPanel' => $filterPanel,],
                'Badges'
            )
        );
    }


    /**
     * View the form for creating a badge.
     *
     * @api   GET /learning/badge/create/
     * @since 20.2
     */
    public function getCreateForm(): void
    {
        $attachzone = new GenericZone('image', $this->storageMapper);
        $attachzone
            ->setAttachmentsLabel('')
            ->setAttachPrompt('upload a badge')
            ->setMimeFilter('image/*')
            ->setFileVar('image')
            ->setMulti(false)
            ->setThumbnailSize(64)
            ->setModalTitle('Select image');

        $iconRegistry = (new IconRegistry())->prepare();

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/createUpdateForm.twig',
                [
                    'badge' => [],
                    'attachzone' => $attachzone->getTwigContext(),
                    'categories' => $this->categoryMapper->findAll(),
                    'curriculumTaggingEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
                    'iconRegistry' => [
                        'registry' => $iconRegistry->getRegistry(),
                        'pageLength' => IconRegistry::BADGE_ICON_MODAL_PAGELENGTH
                    ]
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
     * @param Model|null $badge
     * @throws MapperException
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     */
    public function postBadge(?Model $badge): void
    {
        $data = json_decode(json_encode($this->request->content->get()), true);

        // TODO figure out a better way to handle backend validation than this
        if (trim($data['name']) === '') {
            throw $this->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        $translator = new Translator\ArrayToModel\MutableFieldsOnly(new CollectorFactory());
        $model      = $translator($data)
            ->setCreatedBy($this->getUser())
            ->setUpdatedBy($this->getUser());

        // Is curriculum tagging disabled?
        // Set an empty collection if so, as that is required to save the badge
        if (!$this->getConfigLoader()->get('curriculum_tagging_enabled')) {
            $model = $model->setCurriculumNodes($this->curriculumNodeMapper->createCollection());
        }

        /* Update an existing instance, if you provided it
         */
        if ($badge) {
            $model->setId($badge->getId());
        }

        /* Explicitly and conclusively choose Icon or Image
         */
        if (Model::AVATAR_ICON === $data['badge-avatar-selector']) {
            $model->setImage(null);
        } else {
            $model->setIcon(null);
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
     * View the form for updating a badge
     *
     * @param Model $badge
     * @since 20.2
     * @api   GET /learning/badge/{badgeId}/update
     */
    public function getUpdateForm(Model $badge): void
    {
        $attachzone = new GenericZone('image', $this->storageMapper);
        $attachzone
            ->setAttachmentsLabel('')
            ->setAttachPrompt('upload a badge')
            ->setMimeFilter('image/*')
            ->setFileVar('image')
            ->setMulti(false)
            ->setThumbnailSize(64)
            ->setModalTitle('Select image')
            ->attachFiles([$badge->getImage()]);

        $iconRegistry = (new IconRegistry())->prepare();

        $this->response->content->set($this->buildHtmlOutput('@learningBadge/createUpdateForm.twig', [
            'badge' => $badge,
            'attachzone' => $attachzone->getTwigContext(),
            'categories' => $this->categoryMapper->findAll(),
            'curriculumTaggingEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
            'iconRegistry' => [
                'registry' => $iconRegistry->getRegistry(),
                'pageLength' => IconRegistry::BADGE_ICON_MODAL_PAGELENGTH
            ]
        ], 'Modify Badge'));
    }


    /**
     * Update a badge from POST data
     *
     * @param Model $badge
     * @throws MapperException
     * @throws \PDOException
     * @throws \Schoolbox\Resource\Exception
     * @throws \UnexpectedValueException
     * @since 20.2
     * @api   POST /learning/badge/{badgeId}/update
     */
    public function postUpdate(Model $badge): void
    {
        $this->postBadge($badge);
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
    private function createAwardsFromRequestBody(Model $badge, string $status): void
    {
        // Users are in the data.
        // So far: it's assumed all staff may award or set a badge as a goal to
        // any other user.
        $data       = json_decode($this->request->content->getRaw(), true);
        $collection = $this->awardMapper->createCollection();
        foreach ($data['users'] as $user) {
            $award = $this->awardMapper->createModel()
                ->setRecipient($this->userMapper->findById($user['id']))
                ->setBadge($badge)
                ->setStatus($status)
                ->setAwardedBy($this->getUser())
                ->setAwardedAt(Alaress_Date_Format::now());
            $collection->add($award);
        }
        $this->awardMapper->save($collection);

        // Return what was just created
        $translator = $this->buildBadgeAwardModelToArrayTranslator();
        $this->response->content->set(
            $this->buildJsonOutput($collection->map($translator))
        );
    }


    /**
     * Withdraw an Awarding of $badge to a specific set of users
     *
     * @param Model  $badge
     * @param string $status
     * @throws \PDOException
     */
    private function postWithdraw(Model $badge, string $status): void
    {
        $userIds = $this->getRequest()->post['userIds'] ?? [];

        /* Empty user list; nothing to do
         */
        if (!\count($userIds)) {
            return;
        }

        /* Must be a valid status
         */
        if (!\in_array($status, [AwardModel::AWARD_STATUS_ACHIEVEMENT, AwardModel::AWARD_STATUS_GOAL], true)) {
            return;
        }

        $this->awardMapper->deleteMostRecentlyAwarded($userIds, $badge, $status);
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
                new ScrubAdapter(),
                new Translator\Links($this->getRouter(), $this->getAuthorizer(), $this->getUser())
            ),
            new Translator\ModelToArray\Links(
                $this->router,
                $this->authorizer,
                $this->getUser()
            )
        );
    }


    /**
     * Builder for the model-to-array translator for badge awards.
     *
     * @return Award\Translator\ModelToArray
     */
    private function buildBadgeAwardModelToArrayTranslator(): Award\Translator\ModelToArray
    {
        $factory = new Award\Translator\ModelToArray\Factory($this->router, $this->authorizer, $this->getUser());
        return $factory->build();
    }
}
