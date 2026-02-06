<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord\AdjustmentLevel;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Page;
use Schoolbox\Filter\Selection;
use Schoolbox\DifferentiationProfile\AdjustmentLevels;

/**
 * A factory for building 'Adjustment level' filter objects
 */
class Factory extends Filter\AbstractFactory
{
    /** @var AdjustmentLevels\Mapper */
    private $adjustmentLevelMapper;

    /**
     * @param Page\Option\Mapper          $optionMapper
     * @param Page\Option\Defaults\Mapper $defaultMapper
     * @param Selection\Mapper            $selectionMapper
     * @param AdjustmentLevels\Mapper            $adjustmentLevelMapper
     */
    public function __construct(
        Page\Option\Mapper $optionMapper,
        Page\Option\Defaults\Mapper $defaultMapper,
        Selection\Mapper $selectionMapper,
        AdjustmentLevels\Mapper $adjustmentLevelMapper
    ) {
        parent::__construct($optionMapper, $defaultMapper, $selectionMapper);
        $this->adjustmentLevelMapper = $adjustmentLevelMapper;
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
            $this->adjustmentLevelMapper->findAll()
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
            new Collection($this->adjustmentLevelMapper->findAllIds())
        );
    }
}
