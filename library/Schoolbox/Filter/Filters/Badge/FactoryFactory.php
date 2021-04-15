<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Badge;

use InvalidArgumentException;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\IFactory;
use Schoolbox\Learning\Badge;
use Schoolbox\User;

/**
 * Factory class responsible for building badge filter factories.
 */
class FactoryFactory extends Filter\AbstractFactoryFactory
{
    /**
     * @inheritdoc
     */
    protected const ENTITY = Badge\Mapper::class;


    /**
     * @inheritdoc
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        switch ($filterDefinitionClass) {
            case Category\Definition::class:
                /** @var Badge\Category\Mapper $badgeMapper */
                $badgeMapper = $this->getMapperFactory()->get(Badge\Category\Mapper::class);
                return new Category\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $badgeMapper
                );

            case CurriculumNode\Definition::class:
                /** @var CurriculumNodeMapper $curriculumNodeMapper */
                $curriculumNodeMapper = $this->getMapperFactory()->get(CurriculumNodeMapper::class);
                return new CurriculumNode\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $curriculumNodeMapper,
                    (bool)$this->configLoader->get('curriculum_tagging_enabled')
                );

            case Name\Definition::class:
                return new Name\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper()
                );

            case AllowAsGoal\Definition::class:
                return new AllowAsGoal\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper()
                );

            case YearLevel\Definition::class:
                return new YearLevel\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getYearLevelMapper()
                );
        }
        throw new InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * Badges have nothing to do with folders, thus `$filters` is returned
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
}
