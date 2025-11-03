<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile;

use Alaress\Web\Controller\Base;
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
use Twig\Error\Error;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Filter;

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
        $adminConfigs         = $this->differentiationProfileConfigMapper->findAll();
        $translation = $this->rosettaStone->translate('differentiationProfile', true, true);

        $route = $this->getRouteIfAuthorized('differentiationProfiles.getDifferentiationProfileData');
        $filterPanel = $this->filterFactoryFactory->buildPanel(
            $this->getUser(),
            \Schoolbox\Filter\Page\Model::PAGE_DIFFERENTIATION_PROFILE,
            $route,
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
            ],
            "Manage $translation"
        );
    }

    public function getDifferentiationProfileData(): void
    {
        $adminConfigs         = $this->differentiationProfileConfigMapper->findAll();

        $profileTranslator = new ModelToArray(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $adminConfigs,
        );

        $request             = $this->requestQuery['filter_selection'] ?: [];
        $page                = \Schoolbox\Filter\Page\Model::PAGE_DIFFERENTIATION_PROFILE;
        $codec               = new \Schoolbox\Filter\Codec($this->filterMapper, $this->filterSelectionMapper);

        $limit = $this->requestQuery->get('limit', $this->mapper::DEFAULT_LIMIT);

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

//
//        $idFinder       = Filter\Filters\DifferentiationProfile\IdFinder::getInstance($this->filterMapper, $this->mapper);
//        $profileIds       = $idFinder->findIdsByFilters($dynamicFilters, $this->getUser());
//
//        if (count($profileIds) > 0) {
//            $cursor = new MapperCursor(
//                $this->mapper,
//                20,
//                [
//                    's.lastname' => $this->mapper::SORT_ORDER_ASC,
//                    's.chosen_name' => $this->mapper::SORT_ORDER_ASC,
//                ],
//                $this->mapper->getPredicateBuilder()
//                             ->in('s.id', $profileIds)
//                            ->any(
//                                $this->mapper->getPredicateBuilder()->isTrue(
//                                    'lpt.contribute_differentiation_profile',
//                                )->isTrue(
//                                    'lps.contribute_differentiation_profile',
//                                )
//                            )
//            );
//            $profiles = $cursor->retrieveResults($this->requestPost['cursor'] ?? null);
//            $data = $profiles->map(
//                fn(Model $profile) => call_user_func($profileTranslator, $profile)
//            );
//            $count   = $cursor->retrieveResultCount();
//            $next    = $cursor->next($profiles);
//            $current = $cursor->current($profiles);
//        } else {
//            $data = [];
//            $count = 0;
//            $next = null;
//            $current = null;
//        }

        $payload = [
            'profiles'             => $data,
        ];
        $this->response = $this->responseAsJson([
            'status' => 200,
            'content' => [
                'data' => $payload,
                'metadata' => [
                    'count'  => $count,
                    'cursor' => [
                        'next'    => $next,
                        'current' => $current,
                    ],
                ],
            ],
        ]);
    }
}
