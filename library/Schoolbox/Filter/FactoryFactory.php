<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Filter;
use Schoolbox\Filter\Option;
use Schoolbox\Filter\Option\Defaults;
use Schoolbox\Filter\Selection;
use Schoolbox\Assessment\Term;
use Schoolbox\GenericCollection;
use Schoolbox\MapperFactory;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DataModel\Project;
use Schoolbox\Filter\Filters;


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
     * A registry of filter factories, along with their construction dependencies.
     *
     * Registered by filter definition class name.
     *
     * @param string $filterDefinitionClass
     * @return IFilterFactory
     * @throws \InvalidArgumentException if no filter definition class not found, or filter factory not registered
     * @throws \ReflectionException If the specified class does not exist
     */
    public function buildFilterFactoryForFilter($filterDefinitionClass)
    {
        if (!class_exists($filterDefinitionClass)) {
            throw new \InvalidArgumentException('No filter definition found for class: ' . $filterDefinitionClass);
        }

        // Registry of filters definitions, and their extra dependencies
        switch ($filterDefinitionClass) {
            case Filters\Assessment\AssessmentType\Definition::class:
                return $this->buildFactory(Filters\Assessment\AssessmentType\Factory::class);
            case Filters\Assessment\Folder\Definition::class:
                return $this->buildFactory(Filters\Assessment\Folder\Factory::class, [
                    'folderMapper' => $this->mapperFactory->get(Folder\Mapper::class),
                ]);
            case Filters\Assessment\FolderUltimate\Definition::class:
                return $this->buildFactory(Filters\Assessment\FolderUltimate\Factory::class, [
                    'folderMapper' => $this->mapperFactory->get(Folder\Mapper::class),
                ]);
            case Filters\Assessment\Term\Definition::class:
                return $this->buildFactory(Filters\Assessment\Term\Factory::class, [
                    'termMapper' => $this->mapperFactory->get(Term\Mapper::class),
                ]);
            case Filters\Assessment\WorkType\Definition::class:
                return $this->buildFactory(Filters\Assessment\WorkType\Factory::class, [
                    'workTypeMapper' => $this->mapperFactory->get(WorkType\Mapper::class),
                ]);
            case Filters\Assessment\WeightingType\Definition::class:
                return $this->buildFactory(Filters\Assessment\WeightingType\Factory::class);
            case Filters\Assessment\UnitImportSpec\Definition::class:
                return $this->buildFactory(Filters\Assessment\UnitImportSpec\Factory::class, [
                    'folderMapper'     => $this->mapperFactory->get(Folder\Mapper::class),
                    'courseImplMapper' => $this->mapperFactory->get(CourseImpl\Mapper::class),
                    'unitSpecMapper'   => $this->mapperFactory->get(UnitSpec\Mapper::class),
                ]);
            case Filters\Assessment\Project\Definition::class:
                return $this->buildFactory(Filters\Assessment\Project\Factory::class, [
                    'folderMapper'   => $this->mapperFactory->get(Folder\Mapper::class),
                    'unitImplMapper' => $this->mapperFactory->get(UnitImpl\Mapper::class),
                    'projectMapper'  => $this->mapperFactory->get(Project\Mapper::class),
                ]);
            case Filters\Assessment\UnitSpec\Definition::class:
                return $this->buildFactory(Filters\Assessment\UnitSpec\Factory::class, [
                    'unitSpecMapper' => $this->mapperFactory->get(UnitSpec\Mapper::class),
                ]);
            case Filters\Assessment\UnitImpl\Definition::class:
                return $this->buildFactory(Filters\Assessment\UnitImpl\Factory::class, [
                    'unitImplMapper' => $this->mapperFactory->get(UnitImpl\Mapper::class),
                ]);
        }
        throw new \InvalidArgumentException('No filter factory registered for class: ' . $filterDefinitionClass);
    }


    /**
     * Build a set of key converters for the specified page
     *
     * @param string $page
     * @return IKeyConverter[]|GenericCollection Indexed by filter.id
     * @throws \InvalidArgumentException
     * @throws ServiceNotFound
     * @throws \ReflectionException If the specified class does not exist
     */
    public function buildKeyConverters($page)
    {
        $keyConverters = $this->createKeyConverterCollection();

        foreach ($this->getFilterMapper()->findAll() as $filter) {

            /** @var IFilterDefinition $class */
            $class = $filter->getClass();

            // Does this filter appear on the specified page?
            if (in_array($page, $class::getPages(), true)) {
                $filterFactory = $this->buildFilterFactoryForFilter($class);
                $keyConverter  = $filterFactory->buildKeyConverter($filter);
                $keyConverters->offsetSet($filter->getId(), $keyConverter);
            }
        }
        return $keyConverters;
    }


    /**
     * Builds a filter panel for the specified user and page, which submits selections to the specified route
     *
     * @param User      $user
     * @param string    $page
     * @param string    $route
     * @param null|User $viewee
     * @param array     $query
     * @return Panel
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException If the specified class does not exist
     */
    public function buildPanel(User $user, $page, $route, User $viewee = null, array $query = [])
    {
        $filterComponents = $this->createFilterComponentCollection();
        unset($query['page']);

        foreach ($this->getDefinitions($page) as $className) {
            $filterFactory = $this->buildFilterFactoryForFilter($className);
            $queryKey      = $className::getQueryKey();
            $overrides     = array_key_exists($queryKey, $query) ? explode(',', $query[$queryKey]) : null;
            $component     = $filterFactory->buildComponent($className, $user, $page, $viewee, $overrides);

            // Don't bother inserting component if it wasn't build (because no options would be available anyway)
            if($component){
                $filterComponents->setOffset($className, $component);
            }

        }

        return new Panel($route, $filterComponents);
    }


    /**
     * Get the set of filters that are implemented by the specified page
     *
     * @param string $page
     * @return string[]|IFilterDefinition[]
     * @throws \InvalidArgumentException If unable to locate Filter Mapper class
     */
    private function getDefinitions($page)
    {
        $allFilters        = $this->getFilterMapper()->findAll();
        $definitionClasses = [];
        foreach ($allFilters as $filter) {
            /** @var string|IFilterDefinition $definitionClass */
            $definitionClass = $filter->getClass();

            // Does this filter appear on the specified page?
            if (in_array($page, $definitionClass::getPages(), true)) {
                $definitionClasses[] = $definitionClass;
            }
        }
        return $definitionClasses;
    }


    /**
     * @param null|IFilterComponent[] $contents
     * @return GenericCollection|IFilterComponent[]
     */
    private function createFilterComponentCollection(array $contents = [])
    {
        return new GenericCollection(IFilterComponent::class, $contents);
    }


    /**
     * @param null|IKeyConverter[] $contents
     * @return GenericCollection|IKeyConverter[]
     */
    private function createKeyConverterCollection(array $contents = [])
    {
        return new GenericCollection(IKeyConverter::class, $contents);
    }


    /**
     * Builds a filter factory using the base dependencies, plus any factory-specific ones
     *
     * @param            $factoryClass
     * @param array|null $extraArgs
     * @return IFilterFactory|object
     * @throws \InvalidArgumentException
     * @throws \ReflectionException If the specified class does not exist
     */
    private function buildFactory($factoryClass, array $extraArgs = [])
    {
        $args = array_merge([
            'filterMapper'          => $this->getFilterMapper(),
            'filterOptionMapper'    => $this->getFilterOptionMapper(),
            'filterDefaultMapper'   => $this->getFilterDefaultsMapper(),
            'filterSelectionMapper' => $this->getFilterSelectionMapper(),
        ], $extraArgs);

        $reflector = new \ReflectionClass($factoryClass);
        return $reflector->newInstanceArgs($args);
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
