<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\CustomisationRecord;

use InvalidArgumentException;
use Schoolbox\Admin\DifferentiationProfiles\Mapper as DpMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\CampusMapper;
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Mapper as AdjustmentCategoryMapper;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Mapper as AdjustmentLevelMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\CustomisationRecord;
use Schoolbox\Filter;
use Schoolbox\Filter\IFactory;
use Schoolbox\IConfigLoader;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\MapperFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;

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
     * Constructor
     *
     * @param MapperFactory $mapperFactory
     * @param IConfigLoader $configLoader
     * @param RosettaStone  $rosettaStone
     */
    public function __construct(
        MapperFactory $mapperFactory,
        IConfigLoader $configLoader,
        private RosettaStone $rosettaStone
    ) {
        parent::__construct($mapperFactory, $configLoader);
    }
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
                    $this->getMapperFactory()->get(UserMapper::class),
                    $this->rosettaStone,
                );
            case AdjustmentCategory\Definition::class:
                return new AdjustmentCategory\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getMapperFactory()->get(AdjustmentCategoryMapper::class),
                    $this->getMapperFactory()->get(DpMapper::class),
                );
            case AdjustmentLevel\Definition::class:
                return new AdjustmentLevel\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getMapperFactory()->get(AdjustmentLevelMapper::class),
                    $this->getMapperFactory()->get(DpMapper::class),
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
            case WorkType\Definition::class:
                return new WorkType\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getMapperFactory()->get(WorkTypeMapper::class)
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
