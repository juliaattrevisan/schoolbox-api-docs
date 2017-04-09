<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Filter;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\Assessment\Term;
use Schoolbox\Filter\Filters\AssessmentType as AssessmentTypeFilter;
use Schoolbox\Filter\Filters\Folder as FolderFilter;
use Schoolbox\Filter\Filters\Term as TermFilter;
use Schoolbox\Filter\Filters\WorkType as WorkTypeFilter;
use Schoolbox\GenericCollection;
use Schoolbox\MapperFactory;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Resource\Folder;


/**
 * The FilterFactoryFactory is responsible for creating filter factories.
 *
 * Each filter factory knows how to build a filter for the specified context.
 */
class FactoryFactory
{

    /** @var MapperFactory */
    private $mapperFactory;


    /**
     * @param MapperFactory $mapperFactory
     * @throws \InvalidArgumentException
     */
    public function __construct(MapperFactory $mapperFactory)
    {
        $this->mapperFactory = $mapperFactory;
    }


    /**
     * A registry of filter factories. Registered by filter definition class name.
     *
     * @param string $filterDefinitionClass
     * @return IFilterFactory
     * @throws \InvalidArgumentException if no filter definition class not found, or filter factory not registered
     */
    public function buildFilterFactoryForFilter($filterDefinitionClass)
    {
        if (!class_exists($filterDefinitionClass)) {
            throw new \InvalidArgumentException('No filter definition found for class: ' . $filterDefinitionClass);
        }

        // Registry of as filters definitions. Ensures static analysis identifies usages.
        switch ($filterDefinitionClass) {
            case AssessmentTypeFilter\Definition::class:
                return $this->buildAssessmentTypeFilterFactory();
            case FolderFilter\Definition::class:
                return $this->buildFolderFilterFactory();
            case TermFilter\Definition::class:
                return $this->buildTermFilterFactory();
            case WorkTypeFilter\Definition::class:
                return $this->buildWorkTypeFilterFactory();
        }
        throw new \InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * @param null|IFilterComponent[] $contents
     * @return GenericCollection|IFilterComponent[]
     */
    public function createFilterComponentCollection(array $contents = [])
    {
        return new GenericCollection(IFilterComponent::class, $contents);
    }


    /**
     * @param null|IKeyConverter[] $contents
     * @return GenericCollection|IKeyConverter[]
     */
    public function createKeyConverterCollection(array $contents = [])
    {
        return new GenericCollection(IKeyConverter::class, $contents);
    }


    /**
     * @return AssessmentTypeFilter\Factory
     * @throws \InvalidArgumentException
     */
    public function buildAssessmentTypeFilterFactory()
    {
        return new AssessmentTypeFilter\Factory(
            $this->getFilterMapper(),
            $this->getFilterOptionMapper(),
            $this->getFilterDefaultsMapper(),
            $this->getFilterSelectionMapper()
        );
    }


    /**
     * @return FolderFilter\Factory
     * @throws \InvalidArgumentException
     */
    public function buildFolderFilterFactory()
    {
        /** @var Folder\Mapper $folderMapper */
        $folderMapper = $this->mapperFactory->get(Folder\Mapper::class);
        return new FolderFilter\Factory(
            $this->getFilterMapper(),
            $this->getFilterOptionMapper(),
            $this->getFilterDefaultsMapper(),
            $this->getFilterSelectionMapper(),
            $folderMapper
        );
    }


    /**
     * @return TermFilter\Factory
     * @throws \InvalidArgumentException
     */
    public function buildTermFilterFactory()
    {
        /** @var Term\Mapper $termMapper */
        $termMapper = $this->mapperFactory->get(Term\Mapper::class);
        return new TermFilter\Factory(
            $this->getFilterMapper(),
            $this->getFilterOptionMapper(),
            $this->getFilterDefaultsMapper(),
            $this->getFilterSelectionMapper(),
            $termMapper
        );
    }


    /**
     * @return WorkTypeFilter\Factory
     * @throws \InvalidArgumentException
     */
    public function buildWorkTypeFilterFactory()
    {
        /** @var WorkType\Mapper $workTypeMapper */
        $workTypeMapper = $this->mapperFactory->get(WorkType\Mapper::class);
        return new WorkTypeFilter\Factory(
            $this->getFilterMapper(),
            $this->getFilterOptionMapper(),
            $this->getFilterDefaultsMapper(),
            $this->getFilterSelectionMapper(),
            $workTypeMapper
        );
    }


    /**
     * @return \Schoolbox\Mapper|Filter\Mapper
     * @throws \InvalidArgumentException
     */
    private function getFilterMapper()
    {
        return $this->mapperFactory->get(Filter\Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Option\Mapper
     * @throws \InvalidArgumentException
     */
    private function getFilterOptionMapper()
    {
        return $this->mapperFactory->get(Option\Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Defaults\Mapper
     * @throws \InvalidArgumentException
     */
    private function getFilterDefaultsMapper()
    {
        return $this->mapperFactory->get(Defaults\Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Selection\Mapper
     * @throws \InvalidArgumentException
     */
    private function getFilterSelectionMapper()
    {
        return $this->mapperFactory->get(Selection\Mapper::class);
    }
}
