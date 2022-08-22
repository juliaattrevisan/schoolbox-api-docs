<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Badge;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Alaress\Web\Controller\Base;
use Schoolbox\API\Cursor\MapperCursor;
use Schoolbox\API\Translator\ModelToArray\Composite;
use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Learning\Badge\Translator;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\GenericZone;
use Schoolbox\Resource\Storage;
use Schoolbox\User;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;
use Schoolbox\YearLevel\Model as YearLevel;

/**
 * Controller for badge requests.
 */
class Controller extends Base
{
    // How many badges show per page by default?
    private const BADGES_PER_PAGE = 40;


    /**
     * Expiry options for achievements and goals, with an identifiable key for
     * each.
     *
     * The value labels are in the template: this keeps the presentation logic
     * out of the controller.
     */
    private const EXPIRY_OPTION_VALUES = [
        'never'       => Model::EXPIRY_NEVER,
        'day'         => Model::EXPIRY_DAY,
        'week'        => Model::EXPIRY_WEEK,
        'fortnight'   => Model::EXPIRY_FORTNIGHT,
        'month'       => Model::EXPIRY_MONTH,
        'quarterYear' => Model::EXPIRY_QUARTER_YEAR,
        'halfYear'    => Model::EXPIRY_HALF_YEAR,
        'year'        => Model::EXPIRY_YEAR,
    ];


    /**
     * The badge mapper.
     */
    private Mapper $mapper;


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
     * Mapper for badge categories.
     */
    private Category\Mapper $categoryMapper;


    /**
     * Mapper for year levels.
     */
    private YearLevelMapper $yearLevelMapper;


    /**
     * Mapper for curriculum nodes.
     */
    private CurriculumNodeMapper $curriculumNodeMapper;


    /**
     * Constructor
     *
     * @param Mapper                  $mapper
     * @param Filter\IFactoryFactory  $filterFactoryFactory
     * @param Filter\Mapper           $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param Storage\Mapper          $storageMapper
     * @param Category\Mapper         $categoryMapper
     * @param YearLevelMapper         $yearLevelMapper
     * @param CurriculumNodeMapper    $curriculumNodeMapper
     */
    public function __construct(
        Mapper $mapper,
        Filter\IFactoryFactory $filterFactoryFactory,
        Filter\Mapper $filterMapper,
        Filter\Selection\Mapper $filterSelectionMapper,
        Storage\Mapper $storageMapper,
        Category\Mapper $categoryMapper,
        YearLevelMapper $yearLevelMapper,
        CurriculumNodeMapper $curriculumNodeMapper
    ) {
        $this->mapper                = $mapper;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterMapper          = $filterMapper;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->storageMapper         = $storageMapper;
        $this->categoryMapper        = $categoryMapper;
        $this->yearLevelMapper       = $yearLevelMapper;
        $this->curriculumNodeMapper  = $curriculumNodeMapper;
    }


    /**
     * Retrieve a list of badges as a JSON feed.
     *
     * @api   GET /learning/badge/list/
     * @since 20.2
     */
    public function getBadgeJsonList(): void
    {
        $page = Filter\Page\Model::PAGE_BADGE_LIST;
        $allowAsGoalOnly = $this->request->query->get('sag') ? true : false;

        // Boilerplate for getting a set of badges which match filter
        $qsFilters      = $this->request->query->get('filters', []);
        $codec          = new Codec($this->filterMapper, $this->filterSelectionMapper);
        $user           = $this->getUser();
        $userSelections = $codec->convertRequestToSelections($user, $page, $qsFilters);
        $filters        = $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $user, $page);
        if ($allowAsGoalOnly) {
            $filters = \array_merge($filters, [
                Filter\Filters\Badge\AllowAsGoal\Definition::class => [
                    Filter\Filters\Badge\AllowAsGoal\Definition::KEY_ALLOWED => 1,
                ],
            ]);
        }

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
    }


    /**
     * View the list of badges as a HTML page.
     *
     * This page will contain a Vue  component, which loads the badge JSON feed.
     *
     * @api   GET /learning/badge/
     * @since 20.2
     */
    public function getBadgeListPage(): void
    {
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            Filter\Page\Model::PAGE_BADGE_LIST,
            null,
            $this->getUser(),
            [],
            $this->request->query->getArrayCopy(),
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/list.twig',
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

        $yearLevels = $this->yearLevelMapper->findAll()->map(
            function ($yearLevel) {
                /** @var YearLevel $yearLevel */
                return array_combine(['id', 'name'], $yearLevel->findValueLabelPair());
            }
        );

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningBadge/createUpdateForm.twig',
                [
                    'badge' => [],
                    'curriculumCodes' => [],
                    'attachzone' => $attachzone->getTwigContext(),
                    'categories' => $this->categoryMapper->findAll(),
                    'expiries' => self::EXPIRY_OPTION_VALUES,
                    'yearLevels' => $yearLevels,
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

        if (!$model->getImage() && !$model->getIcon()) {
            throw $this->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
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

        $yearLevels = $this->yearLevelMapper->findAll()->map(
            function ($yearLevel) {
                /** @var YearLevel $yearLevel */
                return array_combine(['id', 'name'], $yearLevel->findValueLabelPair());
            }
        );

        $curriculumCodes = $badge->getCurriculumNodes()?->reindex();
        $this->response->content->set($this->buildHtmlOutput('@learningBadge/createUpdateForm.twig', [
            'badge' => $badge,
            'curriculumCodes' => $curriculumCodes,
            'attachzone' => $attachzone->getTwigContext(),
            'categories' => $this->categoryMapper->findAll(),
            'expiries' => self::EXPIRY_OPTION_VALUES,
            'yearLevels' => $yearLevels,
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
     * Delete a badge.
     *
     * @param Model $badge
     * @throws MapperException
     * @api   DELETE /learning/badge/{badgeId}
     * @since 20.2
     */
    public function deleteBadge(Model $badge): void
    {
        $this->mapper->delete($badge);
    }

    /**
     * Builder for the model-to-array translator for badges.
     *
     * @return Composite
     */
    private function buildModelToArrayTranslator(): Composite
    {
        $prefixGenerator = new PrefixGenerator($this->getConfigLoader());
        return new Composite(
            new Translator\ModelToArray(
                new User\Translator\Author\ModelToArray(),
                new Storage\Translator\ModelToArray($prefixGenerator),
                new ScrubAdapter(),
                new Translator\Links($this->getRouter(), $this->getAuthorizer(), $this->getUser(), $prefixGenerator)
            ),
            new Translator\ModelToArray\Links(
                $this->router,
                $this->authorizer,
                $this->getUser()
            )
        );
    }
}
