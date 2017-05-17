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
     * Build a set of key converters for the specified page
     *
     * @param string $page
     * @return IKeyConverter[]|GenericCollection Indexed by filter.id
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     */
    public function buildKeyConverters($page)
    {
        $keyConverters = $this->createKeyConverterCollection();

        foreach ($this->getFilterMapper()->findAll() as $filter) {

            /** @var IDefinition $class */
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
     * Any overrides in $queryArgs will clobber any saved selections
     *
     * @param User      $user
     * @param string    $page
     * @param string    $route
     * @param null|User $viewee
     * @param array     $queryArgs Indexed by filter.id
     * @return Panel
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined the entity it is filtering
     */
    public function buildPanel(User $user, $page, $route, User $viewee = null, array $queryArgs = [])
    {
        $filterComponents = $this->createFilterComponentCollection();
        unset($queryArgs['page']);

        // Get filters for this factoryFactory's entity type
        $entityFilters = $this->getFilterMapper()->findForEntity(self::getEntity());

        foreach($entityFilters as $filter){
            $filterClass = $filter->getClass();

            // Does the filter not appear on the specified page?
            if (!in_array($page, $filterClass::getPages(), true)) {
                // Don't insert the filter component in panel
                continue;
            }

            $filterFactory = $this->buildFilterFactoryForFilter($filter->getClass());

            // Did controller or URL request to override saved user-selections ?
            $overrides     = array_key_exists($filter->getId(), $queryArgs) ? explode(',', $queryArgs[$filter->getId()]) : null;
            $component     = $filterFactory->buildComponent($filterClass, $user, $page, $viewee, $overrides);

            // Don't bother inserting component if it wasn't build (because no options would be available anyway)
            if ($component) {
                $filterComponents->setOffset($filterClass, $component);
            }
        }

        return new Panel($route, $filterComponents);
    }

    /**
     * @param null|IComponent[] $contents
     * @return GenericCollection|IComponent[]
     */
    private function createFilterComponentCollection(array $contents = [])
    {
        return new GenericCollection(IComponent::class, $contents);
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
     * @return IFactory|object
     * @throws \InvalidArgumentException
     * @throws \ReflectionException If the specified class does not exist
     */
    protected function buildFactory($factoryClass, array $extraArgs = [])
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
     * @return MapperFactory
     */
    protected function getMapperFactory()
    {
        return $this->mapperFactory;
    }


    /**
     * @return \Schoolbox\Mapper|Mapper
     * @throws \InvalidArgumentException If unable to find the mapper class
     */
    private function getFilterMapper()
    {
        return $this->mapperFactory->get(Mapper::class);
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
     * @return \Schoolbox\Mapper|Option\Defaults\Mapper
     * @throws \InvalidArgumentException
     */
    private function getFilterDefaultsMapper()
    {
        return $this->mapperFactory->get(Option\Defaults\Mapper::class);
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
