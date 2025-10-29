<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile;

use Alaress\Web\Controller\Base;
use Schoolbox\DifferentiationProfile\Translator\ModelToArray;
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

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param RosettaStone                 $rosettaStone
     * @param DifferentiationProfileMapper $differentiationProfileMapper
     * @param AdjustmentCategoriesMapper   $adjustmentCategoriesMapper
     * @param AdjustmentLevelsMapper       $adjustmentLevelsMapper
     * @param AdminConfigMapper            $differentiationProifleConfigMapper
     * @param AdminConfigModelToArray      $adminConfigModelToArray
     */
    public function __construct(
        private RosettaStone $rosettaStone,
        private DifferentiationProfileMapper $differentiationProfileMapper,
        private AdjustmentCategoriesMapper $adjustmentCategoriesMapper,
        private AdjustmentLevelsMapper $adjustmentLevelsMapper,
        private AdminConfigMapper $differentiationProifleConfigMapper,
        private AdminConfigModelToArray $adminConfigModelToArray,
        private IFactoryFactory $filterFactoryFactory,
        private \Schoolbox\Filter\Mapper $filterMapper,
        private \Schoolbox\Filter\Selection\Mapper $filterSelectionMapper,
        private Mapper $filteredEntityMapper,
    ) {
    }

    /**
     * Differentiation Profiles management page
     * @return void
     * @throws Error
     */
    public function manageDifferentiationProfiles(): void
    {
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
            ['filterPanel' => $filterPanel],
            "Manage $translation"
        );
    }

    public function getDifferentiationProfileData(): void
    {
        $profiles             = $this->differentiationProfileMapper->findStudentList();
        $adjustmentCategories = $this->adjustmentCategoriesMapper->findAll();
        $adjustmentLevels     = $this->adjustmentLevelsMapper->findAll();
        $adminConfigs         = $this->differentiationProifleConfigMapper->findAll();

        $profileTranslator = new ModelToArray(
            $this->getRouter()->getGenerator(),
            $this->getAuthorizer(),
            $adminConfigs,
        );

        $request             = $this->requestPost['filter_selection'] ?: [];
        $page                = \Schoolbox\Filter\Page\Model::PAGE_DIFFERENTIATION_PROFILE;
        $codec               = new \Schoolbox\Filter\Codec($this->filterMapper, $this->filterSelectionMapper);

        // Convert the request into a set of selections, and update them in the frontend
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        // Convert the set of selections into actual filters
        $dynamicFilters = $this->filterFactoryFactory->convertSelectionsToFilters(
            $selectionsPerFilter,
            $this->getUser(),
            $page,
        );

        $payload = [
            'profiles'             => $profiles->map(
                fn(Model $profile) => call_user_func($profileTranslator, $profile)
            ),
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
        ];
        $this->response = $this->responseAsJson([
            'data' => $payload,
            'metadata' => [],
        ]);
    }
}
