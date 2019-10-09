<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use function count;
use DomainException;
use function in_array;
use Schoolbox\Collection;
use Schoolbox\MapperFactory;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Alaress\Database\Predicate\InvalidColumnException;

abstract class AbstractFactoryFactory implements IFactoryFactory
{

    /**
     * Mapper class of the entity being filtered
     */
    protected const ENTITY = '';

    /** @var MapperFactory */
    private $mapperFactory;


    /**
     * @param MapperFactory $mapperFactory
     */
    public function __construct(MapperFactory $mapperFactory)
    {
        $this->mapperFactory = $mapperFactory;
    }



    /**
     * @return CompositeCodec
     */
    public function buildCodec(): ICodec
    {
        return new Codec($this->getFilterMapper(), $this->getSelectionMapper());
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException             If unable to find the filter mapper class
     * @throws DomainException                       If this class has not defined what entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     * @throws \UnexpectedValueException             If wrong item type inserted in a generic collection
     */
    public function buildEnabledKeyConverters($page): GenericCollection
    {
        $enabledOnly          = true;
        $userConfigurableOnly = false;
        $pageFilters          = $this->buildPageFilters($page, $enabledOnly, $userConfigurableOnly);
        $keyConverters        = $this->createKeyConverterCollection();

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
     * @inheritdoc
     * @throws \InvalidArgumentException             If unable to find the filter mapper class
     * @throws DomainException                       If this class has not defined what entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     * @throws \UnexpectedValueException             If wrong item type inserted in a generic collection
     */
    public function convertSelectionsToFilters(Collection $selectionsPerFilter, User $user, string $page): array
    {
        $keyConverters        = $this->buildEnabledKeyConverters($page);
        $selectionTransformer = new Transformer($keyConverters, $selectionsPerFilter);

        return $selectionTransformer->getByClass($user, $page);
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException             If no filter definition class not found, or filter factory not registered
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     * @throws DomainException                       If attempting to fetch the entity from an abstract factoryFactory
     * @throws \UnexpectedValueException             If wrong item type inserted in a generic collection
     */
    public function buildCurrentState($page, $enabledOnly): GenericCollection
    {
        /** @var GenericCollection|Page\Model[] $savedPageFilters Indexed by filter.id */
        $savedPageFilters = $this->getPageMapper()->findForPage($page)->indexByCallback(function (Page\Model $pageFilter) {
            return $pageFilter->getFilter()->getId();
        });

        // Get all filters for this entity type
        $entityFilters = $this->getFilterMapper()->findForEntity(static::getEntity());

        $definitions = $this->createDefinitionCollection();

        foreach($entityFilters as $filter){
            $class = $filter->getClass();

            // Can this filter never appear on this page?
            if (!in_array($page, $class::getPages(), true)) {
                continue;
            }
            $filterId = $filter->getId();

            // Do we have admin-configured data?
            if($savedPageFilters[$filterId]){
                $pageFilter = $savedPageFilters[$filterId];
            } else {
                $pageFilter = $this->getPageMapper()->createModel();
                $pageFilter
                    ->setFilter($filter)
                    ->setPage($page)
                    ->setEnabled($class::getEnabledInitialState($page))
                    ->setUserConfigurable($class::getUserConfigurableInitialState($page));
            }
            $filter->getPages()[$page] = $pageFilter;

            if ($enabledOnly && !$pageFilter->getEnabled()) {
                continue;
            }

            $factory             = $this->buildFilterFactoryForFilter($class);
            $definition          = $factory->buildDefinition($filter);
            $definitions[$class] = $definition;
            $definition->normalisePageOptions($pageFilter);
        }
        return $definitions;
    }


    /**
     * Builds a filter panel for the specified user and page, which submits selections to the specified route
     *
     * If a visible page-filter has overrides:
     * * any option in the overrides will be present in the page-filter options in the panel
     * * user-selections for the page filter will be replaced by the page-filter's overrides
     *
     * @param User        $user
     * @param string      $page
     * @param null|string $route      Where to post to when the Apply Filters button is clicked.
     *                                If null, don't post.
     * @param null|User   $viewee
     * @param array       $routeArgs  Any route params, as received by the controller's public API method
     * @param array       $request Indexed by filter.id
     * @return Panel
     * @throws \InvalidArgumentException             If unable to locate Filter mapper class, or required filter definition classes
     * @throws DomainException                       If this class has not defined the entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     * @throws InvalidColumnException                If unable to parse a PB's column
     * @throws \UnderflowException                   If we generate an empty PB IN query
     * @throws \UnexpectedValueException             If wrong item type inserted in a generic collection
     */
    public function buildPanel(User $user, string $page, ?string $route, ?User $viewee, array $routeArgs, array $request = []): Panel
    {
        // Clean up aura-inserted items (do this as low down in logic as possible - not in controller)
        if (array_key_exists('page', $request)) {
            unset($request['page']);
        }

        $enabledOnly          = true;
        $userConfigurableOnly = false;
        $enabledPageFilters   = $this->buildPageFilters($page, $enabledOnly, $userConfigurableOnly);

        // Build panel components for all enabled page filters, even if hard-coded (we will store this client side in hidden fields)
        // Ignore anything in $request that is not for a visible page filter
        $filterComponents     = $this->createComponentCollection();

        // Which of the enabled page-filters are configurable by the end user?
        /** @var GenericCollection|Page\Model[] $userConfigurablePageFilters */
        $userConfigurablePageFilters = $enabledPageFilters->filter(function(Page\Model $pageFilter){
            return $pageFilter->getUserConfigurable();
        });

        // We cannot merely replace userSelections here. That must happen later.
        // This is because overrideSelections can insert options, but userSelections cannot
        $codec      = $this->buildCodec();
        $overrides  = $codec->convertRequestToSelections($user, $page, $request);
        $selections = $this->findUserSelections($overrides, $userConfigurablePageFilters, $user, $page);

        // Build Panel Components
        foreach ($enabledPageFilters as $pageFilter) {
            $filter     = $pageFilter->getFilter();
            $class      = $filter->getClass();
            $factory    = $this->buildFilterFactoryForFilter($class);
            $definition = $factory->buildDefinition($filter);
            $definition->normalisePageOptions($pageFilter, $user->getRole()->getType());

            // Note that we *do* need to pass all URL overrides to each filter's panel component.
            // This is because the nullable context filters need to check for inserted folders so they can
            // load the related nullable context (eg UnitImport, Project etc)
            $filterComponents[$class] = $factory->buildComponent(
                $definition,
                $user,
                $pageFilter,
                $selections[$filter->getId()],
                $viewee,
                $routeArgs,
                $overrides
            );
        }
        return new Panel($page, $route, $filterComponents);
    }


    /**
     * Return the mapper class of the entity being filtered by this FactoryFactory
     *
     * @return string
     * @throws DomainException If called on abstract, or concrete class that does not override
     */
    private static function getEntity(): string
    {
        if ('' === static::ENTITY) {
            throw new DomainException('Concrete classes must define this constant');
        }
        return static::ENTITY;
    }


    /**
     * @param Collection|GenericCollection[]|Selection\Model[][] $overrideSelections Indexed by filter.id, then selection.key
     * @param GenericCollection|Page\Model[]                     $visiblePageFilters
     * @param User                                               $user
     * @param string                                             $page
     * @return Collection|GenericCollection[]|Selection\Model[][] Indexed by filter.id, then selection.key
     * @throws \InvalidArgumentException             If selection mapper class not found
     * @throws InvalidColumnException                If unable to parse a PB's column
     * @throws \UnderflowException                   If we generate an empty PB IN query
     */
    private function findUserSelections(Collection $overrideSelections, GenericCollection $visiblePageFilters, User $user, $page): Collection
    {
        /** @var int[] $visibleFilterIds */
        $visibleFilterIds = $visiblePageFilters->map(function (Page\Model $pageFilter) {
            return $pageFilter->getFilter()->getId();
        })->getAll();

        // Filters configured by admin as enabled and user-configurable, and have not been overridden
        $filterIds = array_diff($visibleFilterIds, $overrideSelections->keys());

        // If ignoring all saved user-selections (because everything is overridden)
        if (0 === count($filterIds)) {
            return new Collection();
        }

        $pb = $this->getSelectionMapper()->getPredicateBuilder();
        $pb
            ->in('filter_id', $filterIds)
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
     * @param bool   $enabledOnly          Only include page-filters that are enabled
     * @param bool   $userConfigurableOnly Only include page-filters that are user-configurable
     * @return Page\Model[]|GenericCollection Indexed by page.filter.id
     * @throws \InvalidArgumentException If PageFilter mapper class not found
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @throws DomainException If attempting to fetch the entity for an abstract factory factory
     */
    private function buildPageFilters($page, $enabledOnly, $userConfigurableOnly): GenericCollection
    {
        // What pageFilters could appear on this page ?
        /** @var GenericCollection|Model[] $availableFilters */
        $availableFilters = $this->getFilterMapper()->findForEntity(static::getEntity())->filter(function (Model $instance) use ($page) {
            $definitionClass = $instance->getClass();
            return in_array($page, $definitionClass::getPages(), true);
        });

        $pageFilterMapper = $this->getPageMapper();

        // What pageFilters have been configured by the admin ?
        // Note we cannot filter by $enabledOnly, $userConfigurableOnly here, as we want explicitly disabled items
        // to not be ignored later when we insert initial state to fill missing data
        /** @var GenericCollection|Page\Model[] $savedPageFilters Indexed by filter.id */
        $savedPageFilters = $pageFilterMapper->findForPage($page)->indexByCallback(function (Page\Model $pageFilter) {
            return $pageFilter->getFilter()->getId();
        });

        // And what page filters will we actually show to the end-user ?
        $pageFilters = $pageFilterMapper->createCollection();

        foreach ($availableFilters as $instance) {
            $filterId = $instance->getId();
            $class    = $instance->getClass();

            $pageFilter = $savedPageFilters[$filterId];

            if (!$pageFilter) {
                $pageFilter = $pageFilterMapper->createModel();
                $pageFilter
                    ->setFilter($instance)
                    ->setPage($page)
                    ->setEnabled($class::getEnabledInitialState($page))
                    ->setUserConfigurable($class::getUserConfigurableInitialState($page));
            }

            // Skip if not enabled
            if ($enabledOnly && !$pageFilter->getEnabled()) {
                continue;
            }
            // Skip if not user-configurable
            if($userConfigurableOnly && !$pageFilter->getUserConfigurable()){
                continue;
            }
            $pageFilters[$filterId]      = $pageFilter;
            $instance->getPages()[$page] = $pageFilter;
        }
        return $pageFilters;
    }


    /**
     * @param null|IDefinition[] $contents
     * @return GenericCollection|IDefinition[]
     * @throws \UnexpectedValueException             If wrong item type inserted in a generic collection
     */
    private function createDefinitionCollection(array $contents = []): GenericCollection
    {
        return new GenericCollection(IDefinition::class, $contents);
    }


    /**
     * @param null|IKeyConverter[] $contents
     * @return GenericCollection|IKeyConverter[]
     * @throws \UnexpectedValueException             If wrong item type inserted in a generic collection
     */
    private function createKeyConverterCollection(array $contents = []): GenericCollection
    {
        return new GenericCollection(IKeyConverter::class, $contents);
    }


    /**
     * @param null|IComponent[] $contents
     * @return GenericCollection|IComponent[]
     * @throws \UnexpectedValueException             If wrong item type inserted in a generic collection
     */
    private function createComponentCollection(array $contents = []): GenericCollection
    {
        return new GenericCollection(IComponent::class, $contents);
    }


    /**
     * @return MapperFactory
     */
    protected function getMapperFactory(): MapperFactory
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
    protected function getOptionMapper()
    {
        return $this->mapperFactory->get(Page\Option\Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Page\Option\Defaults\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    protected function getDefaultsMapper()
    {
        return $this->mapperFactory->get(Page\Option\Defaults\Mapper::class);
    }


    /**
     * @return \Schoolbox\Mapper|Selection\Mapper
     * @throws \InvalidArgumentException If mapper class not found
     */
    protected function getSelectionMapper()
    {
        return $this->mapperFactory->get(Selection\Mapper::class);
    }
}
