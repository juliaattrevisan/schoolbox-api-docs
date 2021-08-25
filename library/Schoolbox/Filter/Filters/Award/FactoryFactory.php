<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Award;

use InvalidArgumentException;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters;
use Schoolbox\Filter\IFactory;
use Schoolbox\Learning\Badge;
use Schoolbox\User;

/**
 * Factory class responsible for building award filter factories.
 */
class FactoryFactory extends Filter\AbstractFactoryFactory
{
    /**
     * @inheritdoc
     */
    protected const ENTITY = Badge\Award\Mapper::class;


    /**
     * @inheritdoc
     */
    public function buildFilterFactoryForFilter(string $filterDefinitionClass): IFactory
    {
        switch ($filterDefinitionClass) {
            case Category\Definition::class:
                return new Category\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
                    $this->getBadgeCategoryMapper()
                );
            case Status\Definition::class:
                return new Status\Factory(
                    $this->getOptionMapper(),
                    $this->getDefaultsMapper(),
                    $this->getSelectionMapper(),
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

    /**
     * @return TermMapper
     * @throws InvalidArgumentException
     */
    private function getTermMapper(): TermMapper
    {
        /** @var TermMapper $termMapper */
        $termMapper = $this->getMapperFactory()->get(TermMapper::class);
        return $termMapper;
    }

    /**
     * @return Badge\Category\Mapper
     * @throws InvalidArgumentException
     */
    private function getBadgeCategoryMapper(): Badge\Category\Mapper
    {
        /** @var Badge\Category\Mapper $badgeCategoryMapper */
        $badgeCategoryMapper = $this->getMapperFactory()->get(Badge\Category\Mapper::class);
        return $badgeCategoryMapper;
    }
}
