<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\MapperFactory;
use Schoolbox\GenericCollection;
use Schoolbox\User;

abstract class AbstractFactoryFactory implements IFactoryFactory
{

    /**
     * Mapper class of the entity being filtered
     */
    const ENTITY = '';

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
     * Return the mapper class of the entity being filtered by this FactoryFactory
     *
     * @return string
     * @throws \DomainException If called on abstract, or concrete class that does not override
     */
    public static function getEntity()
    {
        if ('' === static::ENTITY) {
            throw new \DomainException('Concrete classes must define this constant');
        }
        return static::ENTITY;
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     */
    public function buildKeyConverters($page)
    {
        $pageFilters   = $this->buildVisiblePageFilters($page);
        $keyConverters = $this->createKeyConverterCollection();

        /** @var Page\Model[] $pageFilters */
        foreach ($pageFilters as $pageFilter) {
            $filter        = $pageFilter->getFilter();
            $filterFactory = $this->buildFilterFactoryForFilter($filter->getClass());
            $keyConverter  = $filterFactory->buildKeyConverter($filter);
            $keyConverters->offsetSet($filter->getId(), $keyConverter);
        }
        return $keyConverters;
    }


    /**
     * Builds a filter panel for the specified user and page, which submits selections to the specified route
     *
     * If a visible page-filter has overrides:
     * * any option in the overrides will be present in the page-filter options in the panel
     * * user-selections for the page filter will be replaced by the page-filter's overrides
     *
     * @param User      $user
     * @param string    $page
     * @param string    $route
     * @param null|User $viewee
     * @param array     $request Indexed by filter.id
     * @return Panel
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined the entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     */
    public function buildPanel(User $user, $page, $route, User $viewee = null, array $request = [])
    {
        // Clean up aura-inserted items
        if (array_key_exists('page', $request)) {
            unset($request['page']);
        }

        $codec            = new Codec();
        $factories        = $this->createFactoryCollection();
        $keyConverters    = $this->createKeyConverterCollection();
        $filterComponents = $this->createComponentCollection();

        // Only build panel components for visible page filters. Hard-coding is handles on the AJAX request.
        // Ignore anything in $request that is not for a visible page filter
        $visiblePageFilters = $this->buildVisiblePageFilters($page);

        // Iteration 1/2 - build Factories and KeyConverters
        foreach ($visiblePageFilters as $pageFilter) {
            $filter                   = $pageFilter->getFilter();
            $filterId                 = $filter->getId();
            $class                    = $filter->getClass();
            $factories[$filterId]     = $this->buildFilterFactoryForFilter($class);
            $keyConverters[$filterId] = $factories[$filterId]->buildKeyConverter($filter);
        }

        // We cannot merely replace userSelections here. That must happen later.
        // This is because overrideSelections can insert options, but userSelections cannot
        $overrideSelections = $codec->buildSelections($this->getSelectionMapper(), $keyConverters, $user, $page, $request);
        $userSelections     = $this->findUserSelections($overrideSelections, $visiblePageFilters, $user, $page);

        // Iteration 2/2 - build Panel Components
        foreach ($visiblePageFilters as $pageFilter) {
            $filter     = $pageFilter->getFilter();
            $filterId   = $filter->getId();
            $class      = $filter->getClass();
            $definition = $factories[$filterId]->buildDefinition($filter);
            $definition->normalisePageOptions($pageFilter, $user->getRole()->getType());

            $selections = $userSelections[$filter->getId()] ?: $this->getSelectionMapper()->createCollection();

            // Did controller or URL request to override saved user-selections ?
            $overrides = $overrideSelections[$filter->getId()] ?: null;

            $filterComponents[$class] = $factories[$filterId]->buildComponent($definition, $user, $pageFilter, $viewee, $selections, $overrides);
        }
        return new Panel($route, $filterComponents);
    }


    /**
     * @param GenericCollection|Selection\Model[][] $overrideSelections Indexed by filter.id, then selection.key
     * @param GenericCollection|Page\Model[]        $visiblePageFilters
     * @param User                                  $user
     * @param string                                $page
     * @return GenericCollection|Selection\Model[][] Indexed by filter.id, then selection.key
     * @throws \InvalidArgumentException If selection mapper class not found
     */
    private function findUserSelections(GenericCollection $overrideSelections, GenericCollection $visiblePageFilters, User $user, $page)
    {
        /** @var int[] $visibleFilterIds */
        $visibleFilterIds = $visiblePageFilters->map(function (Page\Model $pageFilter) {
            return $pageFilter->getFilter()->getId();
        })->getAll();

        // Filters configured by admin as enabled and user-configurable, and have not been overridden
        $filterIds = array_diff($visibleFilterIds, $overrideSelections->keys());

        $pb = $this->getSelectionMapper()->getPredicateBuilder();
        $pb
            ->equals('filter_id', $filterIds)
            ->equals('user_id', $user->getId())
            ->equals('page', $page);

        /** @var GenericCollection|Selection\Model[][] $userSelections */
        $userSelections = $this->getSelectionMapper()->findByPredicates($pb);

        $userSelections = $userSelections->groupByCallback(function (Selection\Model $selection) {
            return $selection->getFilter()->getId();
        });

        foreach ($userSelections as $filterSelections) {
            $filterSelections->indexBy('key');
        }
        return $userSelections;
    }


    /**
     * Fetch visible page filters from DB, and insert any missing logical items.
     *
     * @param string $page
     * @return Page\Model[]|GenericCollection Indexed by page.filter.id
     * @throws \InvalidArgumentException If PageFilter mapper class not found
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     */
    private function buildVisiblePageFilters($page)
    {
        // What pageFilters could appear on this page ?
        /** @var GenericCollection|Model[] $availableFilters */
        $availableFilters = $this->getFilterMapper()->findAll()->filter(function (Model $instance) use ($page) {
            $definitionClass = $instance->getClass();
            return in_array($page, $definitionClass::getPages(), true);
        });

        $pageFilterMapper = $this->getPageMapper();

        // What pageFilters have been configured by the admin ?
        /** @var GenericCollection|Page\Model[] $savedPageFilters Indexed by filter.id */
        $savedPageFilters = $pageFilterMapper->findForPage($page)->indexByCallback(function (Page\Model $pageFilter) {
            return $pageFilter->getFilter()->getId();
        });

        // And what page filters will we actually show to the end-user ?
        $visiblePageFilters = $pageFilterMapper->createCollection();

        foreach ($availableFilters as $instance) {
            $filterId = $instance->getId();

            $pageFilter = $savedPageFilters[$filterId];

            if (!$pageFilter) {
                $pageFilter = $pageFilterMapper->createModel();
                $pageFilter
                    ->setFilter($instance)
                    ->setPage($page)
                    ->setEnabled(Page\Model::getEnabledInitialState($instance, $page))
                    ->setUserConfigurable(Page\Model::getUserConfigurableInitialState($instance, $page));
            }

            if ($pageFilter->getEnabled() && $pageFilter->getUserConfigurable()) {
                $visiblePageFilters[$filterId] = $pageFilter;
                $instance->getPages()[$page]   = $pageFilter;
            }
        }
        return $visiblePageFilters;
    }


    /**
     * @param null|IFactory[] $contents
     * @return GenericCollection|IFactory[]
     */
    private function createFactoryCollection(array $contents = [])
    {
        return new GenericCollection(IFactory::class, $contents);
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
     * @param null|IComponent[] $contents
     * @return GenericCollection|IComponent[]
     */
    private function createComponentCollection(array $contents = [])
    {
        return new GenericCollection(IComponent::class, $contents);
    }


    /**
     * Builds a filter factory using the base dependencies, plus any factory-specific ones
     *
     * @param            $factoryClass
     * @param array|null $extraArgs
     * @return IFactory|object
     * @throws \InvalidArgumentException
     * @throws \ReflectionException If the specified class does not exist
     */
    protected function buildFactory($factoryClass, array $extraArgs = [])
    {
        $args = array_merge([
            'filterMapper'          => $this->getFilterMapper(),
            'filterPageMapper'      => $this->getPageMapper(),
            'filterOptionMapper'    => $this->getOptionMapper(),
            'filterDefaultMapper'   => $this->getDefaultsMapper(),
            'filterSelectionMapper' => $this->getSelectionMapper(),
        ], $extraArgs);

        $reflector = new \ReflectionClass($factoryClass);
        return $reflector->newInstanceArgs($args);
    }


    /**
     * @return MapperFactory
     */
    protected function getMapperFactory()
    {
        return $this->mapperFactory;
    }


    /**
     * @return \Schoolbox\Mapper|Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getFilterMapper()
    {
        return $this->mapperFactory->get(Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Page\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getPageMapper()
    {
        return $this->mapperFactory->get(Page\Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Page\Option\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getOptionMapper()
    {
        return $this->mapperFactory->get(Page\Option\Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Page\Option\Defaults\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getDefaultsMapper()
    {
        return $this->mapperFactory->get(Page\Option\Defaults\Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Selection\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    private function getSelectionMapper()
    {
        return $this->mapperFactory->get(Selection\Mapper::class);
    }
}
