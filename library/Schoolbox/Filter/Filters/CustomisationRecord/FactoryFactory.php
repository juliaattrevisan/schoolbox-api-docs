<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord;

use InvalidArgumentException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\CampusMapper;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Mapper as AdjustmentCategoryMapper;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Mapper as AdjustmentLevelMapper;
use Schoolbox\CustomisationRecord;
use Schoolbox\Filter;
use Schoolbox\Filter\IFactory;
use Schoolbox\User;

/**
 * Factory class responsible for building customisation record filter factories.
 */
class FactoryFactory extends Filter\AbstractFactoryFactory
{
    /**
     * @inheritdoc
     */
    protected const ENTITY = CustomisationRecord\Mapper::class;


    /**
     * @inheritdoc
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        switch ($filterDefinitionClass) {
            case Name\Definition::class:
                return new Name\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                );
            case AdjustmentCategory\Definition::class:
                return new AdjustmentCategory\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getMapperFactory()->get(AdjustmentCategoryMapper::class)
                );
            case AdjustmentLevel\Definition::class:
                return new AdjustmentLevel\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getMapperFactory()->get(AdjustmentLevelMapper::class)
                );
            case Campus\Definition::class:
                return new Campus\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getMapperFactory()->get(CampusMapper::class)
                );
            case YearLevel\Definition::class:
                return new YearLevel\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getYearLevelMapper()
                );
            case Term\Definition::class:
                return new Term\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getTermMapper()
                );
        }
        throw new InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * Customisation records have nothing to do with folders, thus `$filters` is returned
     * unchanged.
     *
     * @param array $filters
     * @param User  $viewee
     * @return array
     */
    public function checkTeacherFolders(array $filters, User $viewee): array
    {
        return $filters;
    }

    /**
     * @return TermMapper
     * @throws InvalidArgumentException
     */
    private function getTermMapper(): TermMapper
    {
        $termMapper = $this->getMapperFactory()->get(TermMapper::class);
        return $termMapper;
    }
}
