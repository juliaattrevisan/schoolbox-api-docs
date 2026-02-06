<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile;

use Alaress\Web\Controller\Base;
use Alaress\Web\Request\Values;
use Schoolbox\API\Cursor\MapperCursor;
use Schoolbox\Collection;
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
     * @param RosettaStone                                              $rosettaStone
     * @param AdjustmentCategoriesMapper                                $adjustmentCategoriesMapper
     * @param AdjustmentLevelsMapper                                    $adjustmentLevelsMapper
     * @param AdminConfigMapper                                         $differentiationProfileConfigMapper
     * @param AdminConfigModelToArray                                   $adminConfigModelToArray
     * @param IFactoryFactory                                           $filterFactoryFactory
     * @param Mapper                                                    $filterMapper
     * @param Filter\Selection\Mapper                                   $filterSelectionMapper
     * @param DifferentiationProfileMapper                              $mapper
     * @param PastoralMapper                                            $pastoralMapper
     * @param \Schoolbox\Pastoral\ReportBuilder\Translator\ModelToArray $pastoralTranslator
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
        $translation          = $this->rosettaStone->translate('differentiationProfile', true, true);

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
                'routes' => [
                    'getData' => $this->getRouteIfAuthorized('differentiationProfiles.getAllDifferentiationProfileData'),
                    'genAi' => $this->getRouteIfAuthorized('openai.postDifferentiation'),
                ]
            ],
            "Manage $translation"
        );
    }

    public function getAllDifferentiationProfileData(): void
    {
        $adminConfigs         = $this->differentiationProfileConfigMapper->findByColumn('is_enabled', 1);

        $profileTranslator = new ModelToArray(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $this->getUser(),
            $adminConfigs,
        );

        $request             = $this->requestQuery['filter_selection'] ?? [];

        if (!is_array($request)) {
            $this->sendJsonError('Invalid filter selection format.', 400);
            return;
        }

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

        $orderBy = $this->requestQuery->get('sortBy', Cursor::CURSOR_DEFAULT_ORDER_BY);
        $order = strtolower($this->requestQuery->get('order', $this->mapper::SORT_ORDER_ASC));

        $sortOrder = match ($orderBy) {
            Cursor::CURSOR_ORDER_BY_UPDATED_AT => [
                'differentiation_profiles.updated_at' => $order,
                'p.student_id'  => $order,
            ],
            /* Default to sorting by student name
             */
            default => [
                's.lastname'    => $order,
                's.chosen_name' => $order,
                'p.student_id'  => $order,
            ]
        };

        $cursor = new Cursor(
            $this->getUser(),
            $finder,
            $dynamicFilters,
            $orderBy,
            $sortOrder,
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
     * @param int|null $studentId
     * @return void
     */
    public function getLinkedPastoralRecords(?int $studentId): void
    {
        if (null === $studentId) {
            $this->sendJsonError('student id is missing.', 400);
            return;
        }

        $pb = $this->mapper->filterByPermission(
            $this->mapper->getPredicateBuilder(),
            $this->getUser()
        );

        $isFlag = $this->requestQuery->get('isFlag') === 'true' || $this->requestQuery->get('isFlag') === true;
        $typeId = $this->requestQuery->get('typeId') !== null && is_numeric($this->requestQuery->get('typeId')) ? (int)$this->requestQuery->get('typeId') : null;
        $subtypeId = $this->requestQuery->get('subtypeId') !== null && is_numeric($this->requestQuery->get('subtypeId')) ? (int)$this->requestQuery->get('subtypeId') : null;

        $where = '';
        if (\count($pb->getWhere())) {
            $where = 'AND ' . implode(' AND ', $pb->getWhere());
        }

        $params = ['studentId' => $studentId];

        if ($isFlag) {
            /* When isFlag is true, filter by type_id and subtype_id instead of contribute_differentiation_profile
             */
            $typeConditions = [];

            if (null !== $typeId) {
                $typeConditions[] = 'p.type_id = :typeId';
                $params['typeId'] = $typeId;
            }

            if (null !== $subtypeId) {
                $typeConditions[] = 'p.subtype_id = :subtypeId';
                $params['subtypeId'] = $subtypeId;
            }

            $typeFilter = !empty($typeConditions) ? 'AND (' . implode(' AND ', $typeConditions) . ')' : '';

            $query = "
                SELECT p.id
                FROM pastoral p
                LEFT JOIN pastoral_access pa ON p.id = pa.pastoral_id
                LEFT JOIN lu_pastoral_type lpt ON p.type_id = lpt.id
                LEFT JOIN lu_pastoral_subtype lps ON p.subtype_id = lps.id
                WHERE p.student_id = :studentId
                {$typeFilter}
                AND p.deleted_at IS NULL
                AND (p.expires_at >= CURDATE() OR p.expires_at IS NULL)
                {$where}
            ";
        } else {
            /* Original query with contribute_differentiation_profile check
             */
            $query = "
                SELECT p.id
                FROM pastoral p
                LEFT JOIN pastoral_access pa ON p.id = pa.pastoral_id
                LEFT JOIN lu_pastoral_type lpt ON p.type_id = lpt.id
                LEFT JOIN lu_pastoral_subtype lps ON p.subtype_id = lps.id
                WHERE p.student_id = :studentId
                AND (lpt.contribute_differentiation_profile OR lps.contribute_differentiation_profile)
                AND p.deleted_at IS NULL
                AND (p.expires_at >= CURDATE() OR p.expires_at IS NULL)
                {$where}
            ";
        }

        $params = array_merge($params, $pb->getParams());

        $pastoralIds = $this->pastoralMapper->getDatabase()->executeQuery($query, $params)->fetchColumnArray('id');

        if (\count($pastoralIds) > 0) {
            $cursor = new MapperCursor(
                $this->pastoralMapper,
                $this->mapper::DEFAULT_LIMIT,
                /* Sort the pastoral records by the most recently created first
                 */
                ['created_at' => PastoralMapper::SORT_ORDER_DESC],
                $this->pastoralMapper->getPredicateBuilder()
                                     ->in('id', $pastoralIds)
            );

            $results = $cursor->retrieveResults($this->requestQuery->get('cursor'));
            $data    = $results->map($this->pastoralTranslator);
            $count   = $cursor->retrieveResultCount();
            $next    = $cursor->next($results);
            $current = $cursor->current($results);
        } else {
            $data    = [];
            $count   = 0;
            $next    = null;
            $current = null;
        }

        $this->response = $this->responseAsJson([
            'data'     => [
                'results' => $data,
            ],
            'metadata' => [
                'count'  => $count,
                'cursor' => [
                    'next'    => $next,
                    'current' => $current,
                ],
            ],
        ]);
    }

    /**
     * Create differentiation profile data
     *
     * @param User $student
     * @return void
     * @throws Exception
     */
    public function createDifferentiationProfileData(User $student): void
    {
        $post = $this->requestPost;

        /* Check if a profile already exists for the student
         */
        try {
            $this->mapper->findOneByColumn('student_id', $student->getId());
            $this->sendJsonError('Profile for student already exists.', 400);
            return;
        } catch (Exception\NotFound $e) {
            /* No existing profile found, proceed to create a new one
             */
        }

        try {
            $model = $this->mapper->createModel()
                                  ->setStudent($student);
        } catch (\Exception $e) {
            $this->sendJsonError('Failed to create differentiation profile record.', 500);
            return;
        }

        $this->persistDifferentiationProfile($model, $post);
    }

    /**
     * Update differentiation profile data
     *
     * @param User $student
     * @return void
     * @throws Exception
     */
    public function putDifferentiationProfileData(User $student): void
    {
        $body = new Values($this->request->getParsedBody() ?? []);

        try {
            /** @var Model $model */
            $model = $this->mapper->findOneByColumn(
                'student_id',
                $student->getId(),
                [
                    'order' => [
                        'updated_at' => DifferentiationProfileMapper::SORT_ORDER_DESC,
                    ],
                ]
            );
        } catch (\Exception $e) {
            $this->sendJsonError('Differentiation profile record not found.', 404);
            return;
        }

        $this->persistDifferentiationProfile($model, $body);
    }

    /**
     * Persist differentiation profile data
     *
     * @param Model  $model
     * @param Values $post
     * @return void
     * @throws Exception
     */
    private function persistDifferentiationProfile(Model $model, Values $post): void
    {
        $adjustmentLevel = $post->get('adjustment_level');
        if (null !== $adjustmentLevel) {
            /* Ensure adjustment level is an array
             */
            if (!is_array($adjustmentLevel)) {
                $this->sendJsonError('Adjustment level must be an array.', 400);
                return;
            }

            if (!$this->validateAdjustmentIds($adjustmentLevel, $this->adjustmentLevelsMapper, 'adjustment levels')) {
                return;
            }
        }

        $adjustmentCategory = $post->get('adjustment_category');
        if (null !== $adjustmentCategory) {
            /* Ensure adjustment category is an array
             */
            if (!is_array($adjustmentCategory)) {
                $this->sendJsonError('Adjustment category must be an array.', 400);
                return;
            }

            if (!$this->validateAdjustmentIds($adjustmentCategory, $this->adjustmentCategoriesMapper, 'adjustment categories')) {
                return;
            }
        }

        /* Validate text field lengths
         */
        $maxLength = \Schoolbox\Column\Base::TEXT_MAX_LENGTH;
        $goals = $post->get('goals');
        if (null !== $goals && strlen($goals) > $maxLength) {
            $this->sendJsonError(
                sprintf('Goals field exceeds the maximum allowed length of %d characters.', $maxLength),
                400
            );
            return;
        }
        $overview = $post->get('overview');
        if (null !== $overview && strlen($overview) > $maxLength) {
            $this->sendJsonError(
                sprintf('Overview field exceeds the maximum allowed length of %d characters.', $maxLength),
                400
            );
            return;
        }
        $strategies_adjustments = $post->get('strategies_adjustments');
        if (null !== $strategies_adjustments && strlen($strategies_adjustments) > $maxLength) {
            $this->sendJsonError(
                sprintf('Strategies and Adjustments field exceeds the maximum allowed length of %d characters.', $maxLength),
                400
            );
            return;
        }
        $needs_challenges = $post->get('needs_challenges');
        if (null !== $needs_challenges && strlen($needs_challenges) > $maxLength) {
            $this->sendJsonError(
                sprintf('Needs and Challenges field exceeds the maximum allowed length of %d characters.', $maxLength),
                400
            );
            return;
        }
        $interests_strengths = $post->get('interests_strengths');
        if (null !== $interests_strengths && strlen($interests_strengths) > $maxLength) {
            $this->sendJsonError(
                sprintf('Interests and Strengths field exceeds the maximum allowed length of %d characters.', $maxLength),
                400
            );
            return;
        }

        try {
            /** @var Model $model */
            $model
                ->setAdjustmentLevelIds(
                    $adjustmentLevel ?
                        implode(',', array_column($adjustmentLevel, 'id')) :
                        null
                )
                ->setAdjustmentCategoryIds(
                    $adjustmentCategory ?
                        implode(',', array_column($adjustmentCategory, 'id')) :
                        null
                )
                ->setOverview($post->get('overview'))
                ->setGoals($post->get('goals'))
                ->setStrategiesAdjustments($post->get('strategies_adjustments'))
                ->setNeedsChallenges($post->get('needs_challenges'))
                ->setInterestsStrengths($post->get('interests_strengths'))
                ->setUpdatedBy($this->getUser());

            $isNewRecord = null === $model->getId();
            /* Set created_by only if it's a new record
             */
            if ($isNewRecord) {
                $model->setCreatedBy($this->getUser());
            }

            $this->mapper->save($model);

            /* Retrieve the updated model with the updated adjustments
             */
            $model = $this->mapper->findById($model->getId());

            $profileTranslator = new ModelToArray(
                $this->getRouter()->getGenerator(),
                $this->getAuthorizer(),
                $this->getUser(),
                $this->differentiationProfileConfigMapper->findByColumn('is_enabled', 1),
            );

            $this->response = $this
                ->responseAsJson(call_user_func($profileTranslator, $model))
                ->withStatus($isNewRecord ? 201 : 200);
        } catch (\Exception $e) {
            $this->sendJsonError('Failed to save differentiation profile data.', 500);
        }
    }

    /**
     * Validate adjustment IDs exist in database
     *
     * @param array<array{id: int}> $adjustments
     * @param \Schoolbox\Mapper $adjustmentMapper
     * @param string $type
     * @return bool Returns true if valid, false otherwise (response already set)
     */
    private function validateAdjustmentIds(array $adjustments, \Schoolbox\Mapper $adjustmentMapper, string $type): bool
    {
        $ids = array_column($adjustments, 'id');

        /* Ensure there are IDs to validate
         */
        if (empty($ids)) {
            $this->sendJsonError("IDs for {$type} are not provided.", 400);
            return false;
        }

        try {
            $pb = $adjustmentMapper->getPredicateBuilder();
            $pb->in('id', $ids);
            $count = $adjustmentMapper->countByPredicates($pb);
            /* Check if all provided IDs exist
             */
            if ($count !== count($adjustments)) {
                $this->sendJsonError(
                    'One or more ' . $type . ' are invalid.',
                    400
                );
                return false;
            }
            return true;
        } catch (\Exception $e) {
            $this->sendJsonError('Error updating adjustment ' . $type, 500);
            return false;
        }
    }

    /**
     * View differentiation profile data for a student
     *
     * @param User $student
     * @return void
     * @throws Exception
     */
    public function viewDifferentiationProfileData(User $student): void
    {
        try {
            $profile = $this->mapper->findOneByColumn(
                'student_id',
                $student->getId(),
                [
                    'order' => ['updated_at' => DifferentiationProfileMapper::SORT_ORDER_DESC],
                ]
            );
        } catch (Exception\NotFound $e) {
            $this->sendJsonError('Differentiation profile record not found for the student.', 404);
            return;
        }

        $configs = $this->differentiationProfileConfigMapper->findByColumn('is_enabled', 1)->indexBy('name');

        $configKey = DifferentiationProfileMapper::CONFIG_METHOD_MAPPING;

        $this->response = $this->responseAsJson(
            $configs->reduce(function ($carry, $config) use ($profile, $configKey) {
                /** @var \Schoolbox\Admin\DifferentiationProfiles\Model $config */
                if (array_key_exists($config->getName(), $configKey)) {
                    $method  = $configKey[$config->getName()];
                    $value   = call_user_func([$profile, $method]);

                    if ($config->getModule() === AdminConfigMapper::MODULE_ADJUSTMENTS && $value instanceof Collection) {
                        $value = ($value->count() > 0 ) ?
                            $value->map(fn(AdjustmentCategoriesModel|AdjustmentLevelsModel $adjustment) => [
                                'name' => $adjustment->getName(),
                            ]) : null;
                    }

                    $carry[] = [
                        'terminology' => $config->getTerminology() ?? $config->getTitle(),
                        'value'       => $value
                    ];
                }
                return $carry;
            }, [])
        );
    }

    /**
     * Get differentiation profile data for a student
     *
     * @param User $student
     * @return void
     * @throws Exception
     */
    public function getDifferentiationProfileData(User $student): void
    {
        try {
            $profile = $this->mapper->findOneByColumn(
                'student_id',
                $student->getId(),
                [
                    'order' => ['updated_at' => DifferentiationProfileMapper::SORT_ORDER_DESC],
                ]
            );

            $profileTranslator = new ModelToArray(
                $this->getRouter()->getGenerator(),
                $this->getAuthorizer(),
                $this->getUser(),
                $this->differentiationProfileConfigMapper->findByColumn('is_enabled', 1),
            );
            $data = call_user_func($profileTranslator, $profile);
            $this->response = $this->responseAsJson($data);
            return;
        } catch (Exception\NotFound $e) {
            $this->sendJsonError('Differentiation profile record not found for the student.', 404);
            return;
        }
    }
}
