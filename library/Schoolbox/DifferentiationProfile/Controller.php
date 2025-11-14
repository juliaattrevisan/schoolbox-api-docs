<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile;

use Alaress\Web\Controller\Base;
use Schoolbox\API\Cursor\MapperCursor;
use Schoolbox\DifferentiationProfile\Translator\ModelToArray;
use Schoolbox\Filter\Mapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\DifferentiationProfile\Mapper as DifferentiationProfileMapper;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Mapper as AdjustmentCategoriesMapper;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Mapper as AdjustmentLevelsMapper;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Model as AdjustmentCategoriesModel;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Model as AdjustmentLevelsModel;
use Schoolbox\Admin\DifferentiationProfiles\Translator\ModelToArray as AdminConfigModelToArray;
use Schoolbox\Admin\DifferentiationProfiles\Model as AdminConfigModel;
use Schoolbox\Admin\DifferentiationProfiles\Mapper as AdminConfigMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\User;
use Twig\Error\Error;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Filter;
use Schoolbox\Pastoral\Mapper as PastoralMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param RosettaStone                 $rosettaStone
     * @param AdjustmentCategoriesMapper   $adjustmentCategoriesMapper
     * @param AdjustmentLevelsMapper       $adjustmentLevelsMapper
     * @param AdminConfigMapper            $differentiationProfileConfigMapper
     * @param AdminConfigModelToArray      $adminConfigModelToArray
     * @param IFactoryFactory              $filterFactoryFactory
     * @param Mapper                       $filterMapper
     * @param Filter\Selection\Mapper      $filterSelectionMapper
     * @param DifferentiationProfileMapper $mapper
     */
    public function __construct(
        private RosettaStone $rosettaStone,
        private AdjustmentCategoriesMapper $adjustmentCategoriesMapper,
        private AdjustmentLevelsMapper $adjustmentLevelsMapper,
        private AdminConfigMapper $differentiationProfileConfigMapper,
        private AdminConfigModelToArray $adminConfigModelToArray,
        private IFactoryFactory $filterFactoryFactory,
        private \Schoolbox\Filter\Mapper $filterMapper,
        private \Schoolbox\Filter\Selection\Mapper $filterSelectionMapper,
        private DifferentiationProfileMapper $mapper,
        private PastoralMapper $pastoralMapper,
        private \Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray $pastoralTranslator,
    ) {
    }

    /**
     * Differentiation Profiles management page
     * @return void
     * @throws Error
     */
    public function manageDifferentiationProfiles(): void
    {
        $adjustmentCategories = $this->adjustmentCategoriesMapper->findAll();
        $adjustmentLevels     = $this->adjustmentLevelsMapper->findAll();
        $adminConfigs         = $this->differentiationProfileConfigMapper->findByColumn('is_enabled', 1);
        $translation = $this->rosettaStone->translate('differentiationProfile', true, true);

        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            \Schoolbox\Filter\Page\Model::PAGE_DIFFERENTIATION_PROFILE,
            null,
            $this->getUser(),
            [],
            $this->requestQuery->getArrayCopy(),
        );

        $this->response = $this->responseFromTemplate(
            '@differentiationProfiles/index.twig',
            [
                'filterPanel' => $filterPanel,
                'adjustmentCategories' => $adjustmentCategories->map(
                    fn(AdjustmentCategoriesModel $category) => [
                        'id'   => $category->getId(),
                        'name' => $category->getName(),
                    ]
                ),
                'adjustmentLevels'     => $adjustmentLevels->map(
                    fn(AdjustmentLevelsModel $level) => [
                        'id'   => $level->getId(),
                        'name' => $level->getName(),
                    ]
                ),
                'header'               => $adminConfigs->map(
                    fn(AdminConfigModel $config) => call_user_func($this->adminConfigModelToArray, $config)
                ),
                'route' => $this->getRouteIfAuthorized('differentiationProfiles.getDifferentiationProfileData'),
            ],
            "Manage $translation"
        );
    }

    public function getDifferentiationProfileData(): void
    {
        $adminConfigs         = $this->differentiationProfileConfigMapper->findByColumn('is_enabled', 1);

        $profileTranslator = new ModelToArray(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getUser(),
            $adminConfigs,
        );

        $request             = $this->requestQuery['filter_selection'] ?: [];
        $page                = \Schoolbox\Filter\Page\Model::PAGE_DIFFERENTIATION_PROFILE;
        $codec               = new \Schoolbox\Filter\Codec($this->filterMapper, $this->filterSelectionMapper);

        $limit = (int)$this->requestQuery->get('limit', $this->mapper::DEFAULT_LIMIT);

        // Convert the request into a set of selections, and update them in the frontend
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        // Convert the set of selections into actual filters
        $dynamicFilters = $this->filterFactoryFactory->convertSelectionsToFilters(
            $selectionsPerFilter,
            $this->getUser(),
            $page,
        );

        /** @var Filter\Filters\DifferentiationProfile\Finder $finder */
        $finder = Filter\Filters\DifferentiationProfile\Finder::getInstance($this->filterMapper, $this->mapper);

        $cursor = new Cursor(
            $this->getUser(),
            $finder,
            $dynamicFilters,
            [
                's.lastname'    => $this->mapper::SORT_ORDER_ASC,
                's.chosen_name' => $this->mapper::SORT_ORDER_ASC,
                'p.student_id'  => $this->mapper::SORT_ORDER_ASC,
            ],
            $limit,
        );

        $profiles = $cursor->retrieveResults($this->requestQuery['cursor'] ?? null);

        $data = $profiles->map(
            fn(Model $profile) => call_user_func($profileTranslator, $profile)
        );
        $count = $cursor->retrieveResultCount();
        $current = $cursor->current($profiles);
        $next = $cursor->next($profiles);

        $payload = [
            'profiles'             => $data,
        ];
        $this->response = $this->responseAsJson(
            [
                'data' => $payload,
                'metadata' => [
                    'count'  => $count,
                    'cursor' => [
                        'next'    => $next,
                        'current' => $current,
                    ],
                ],
            ]
        );
    }

    /**
     * Get linked pastoral records for a student that contribute to differentiation profile
     *
     * @return void
     */
    public function getLinkedPastoralRecords(?int $studentId): void
    {
        if (null === $studentId) {
            $this->sendJsonError('student id is missing.', 400);
            return;
        }

        $query = "
            SELECT p.id
            FROM pastoral p
            LEFT JOIN lu_pastoral_type lpt ON p.type_id = lpt.id
            LEFT JOIN lu_pastoral_subtype lps ON p.subtype_id = lps.id
            WHERE p.student_id = :studentId
            AND (lpt.contribute_differentiation_profile OR lps.contribute_differentiation_profile)
            AND p.deleted_at IS NULL
            AND (p.expires_at >= CURDATE() OR p.expires_at IS NULL)
        ";

        $params      = [
            'studentId' => $studentId,
        ];
        $pastoralIds = $this->pastoralMapper->getDatabase()->executeQuery($query, $params)->fetchColumnArray('id');

        $cursor = new MapperCursor(
            $this->pastoralMapper,
            $this->mapper::DEFAULT_LIMIT,
            /* Sort the pastoral records by the most recently created first
             */
            ['created_at' => PastoralMapper::SORT_ORDER_DESC],
            $this->pastoralMapper->getPredicateBuilder()
                                 ->in('id', $pastoralIds)
        );

        $data           = $cursor->retrieveResults($this->requestQuery['cursor'] ?? null);
        $this->response = $this->responseAsJson(
            [
                'data'     => [
                    'results' => $data->map($this->pastoralTranslator),
                ],
                'metadata' => [
                    'count'  => $cursor->retrieveResultCount(),
                    'cursor' => [
                        'next'    => $cursor->next($data),
                        'current' => $cursor->current($data),
                    ],
                ],
            ]
        );
    }

    /**
     * Save differentiation profile data
     *
     * @param User $student
     * @return void
     * @throws Exception
     */
    public function postDifferentiationProfileData(User $student): void
    {
        $post = $this->requestPost;

        $id = $post->getInt('id');

        /* Look for the existing differentiation profile record if an ID is provided
         */
        if (null !== $id) {
            try {
                $model = $this->mapper->findById($id);
            } catch (\Exception $e) {
                $this->sendJsonError('Differentiation profile record not found.', 404);
                return;
            }
        } else {
            $model = $this->mapper->createModel()
                ->setStudent($student);
        }

        try {
            /** @var Model $model */
            $model
                ->setAdjustmentLevel($post->getInt('adjustment_level'))
                ->setAdjustmentCategory($post->getInt('adjustment_category'))
                ->setOverview($post->get('overview'))
                ->setGoals($post->get('goals'))
                ->setStrategiesAdjustments($post->get('strategies_adjustments'))
                ->setNeedsChallenges($post->get('needs_challenges'))
                ->setInterestsStrengths($post->get('interests_strengths'));

            $this->mapper->save($model);

            $this->response = $this->responseEmpty(200);
        } catch (\Exception $e) {
            $this->sendJsonError('Failed to save differentiation profile data.', 500);
        }
    }
}
