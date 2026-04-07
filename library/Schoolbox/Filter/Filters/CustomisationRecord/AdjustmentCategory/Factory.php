<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\AdjustmentCategory;

use Schoolbox\Admin\DifferentiationProfiles\Mapper as DpMapper;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\DifferentiationProfile\AdjustmentLevel\Component;
use Schoolbox\Filter\IComponent;
use Schoolbox\Filter\IDefinition;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Page\Option\Mapper;
use Schoolbox\Filter\Selection;
use Schoolbox\DifferentiationProfile\AdjustmentCategories;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * A factory for building 'Adjustment Category' filter objects
 */
class Factory extends Filter\AbstractFactory
{
    /** @var AdjustmentCategories\Mapper */
    private $adjustmentCategoryMapper;

    /**
     * @param Mapper                      $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param AdjustmentCategories\Mapper $adjustmentCategoryMapper
     * @param DpMapper                    $differentiationProfileMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        AdjustmentCategories\Mapper $adjustmentCategoryMapper,
        private DpMapper $differentiationProfileMapper,
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->adjustmentCategoryMapper = $adjustmentCategoryMapper;
    }

    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Definition|Filter\IDefinition
     */
    public function buildDefinition(Filter\Model $filter): Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper(),
            $this->adjustmentCategoryMapper->findAll()
        );
    }


    /**
     * @inheritdoc
     * @param Filter\Model $filter
     * @return Filter\IKeyConverter|KeyConverter
     */
    public function buildKeyConverter(Filter\Model $filter): Filter\IKeyConverter
    {
        return new KeyConverter(
            $filter,
            new Collection($this->adjustmentCategoryMapper->findAllIds())
        );
    }

    /**
     * @inheritdoc
     */
    public function buildComponent(
        IDefinition $definition,
        User $user,
        Page\Model $pageFilter,
        ?GenericCollection $selections,
        ?User $viewee,
        array $routeArgs,
        GenericCollection $overrides
    ): IComponent {
        return new Component(
            $definition,
            $user,
            $pageFilter,
            $selections,
            $overrides[$pageFilter->getFilter()->getId()],
            $this->getSelectionMapper(),
            $this->differentiationProfileMapper,
        );
    }
}
