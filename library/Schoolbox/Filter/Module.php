<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Authorizer\Rule\IsSuperuser;
use \Alaress\Module\Module as AlaressModule;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Filter\Filters\Assessment\FactoryFactory;
use Schoolbox\Mapper\Exception as MapperException;
use \Twig_Loader_Chain;
use Schoolbox\Filter\Option\Defaults;


class Module extends AlaressModule
{

    /**
     * @param ContainerInterface $injector
     * @param string             $basename
     */
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        parent::__construct($injector, '' === $basename ? 'filters' : $basename);
    }


    /**
     * @inheritdoc
     * @throws NotFound If invalid roleType
     * @throws \InvalidArgumentException
     * @throws ServiceNotFound
     * @throws \ReflectionException If the filter definition class cannot be found in code base
     */
    protected function registerRoutes()
    {
        $isSuper = new IsSuperuser();

        $loadFilters = function () {
            try {
                return [
                    'visibleFilters' => $this->getFilterMapper()->findAll()->filter(function (Model $instance) {
                        $definitionClass = $instance->getClass();
                        return count($definitionClass::getPages()) > 0;
                    }),
                ];
            } catch (MapperException $e) {
                throw new NotFound($e->getMessage());
            }
        };

        $loadDefinition = function (array $params) {
            try {
                $filterId       = $params['filter'];
                $filterInstance = $this->getFilterMapper()->findById($filterId);

                $filterFactoryFactory = null;
                switch ($filterInstance->getEntity()) {
                    case \Schoolbox\Learning\Assessment\DataModel\Mapper::class:
                        $filterFactoryFactory = new FactoryFactory($this->getMapperFactory());
                        break;
                    // Other entity types can have their factory factories registered here
                }
                $definitionClass = $filterInstance->getClass();
                if (0 === count($definitionClass::getPages())) {
                    throw new NotFound("This filter does not appear in UI: $filterId");
                }
                $filterFactory = $filterFactoryFactory->buildFilterFactoryForFilter($definitionClass);
                $definition    = $filterFactory->buildDefinition($filterInstance);
                return [
                    'definition' => $definition,
                ];
            } catch (MapperException $e) {
                throw new NotFound($e->getMessage());
            }
        };

        $optionDependencies = function (array $params) use ($loadDefinition) {
            return array_merge([
                'optionMapper' => $this->getMapperFactory()->get(Option\Mapper::class),
            ], $loadDefinition($params));
        };

        $defaultDependencies = function (array $params) use ($optionDependencies) {
            return array_merge([
                'defaultMapper' => $this->getMapperFactory()->get(Defaults\Mapper::class),
            ], $optionDependencies($params));
        };


        $this->register('GET', '', 'getIndex', $isSuper, $loadFilters);
        $this->register('GET', '/{filter:\d+}/options', 'getAvailableOptions', $isSuper, $loadDefinition);
        $this->register('GET', '/{filter:\d+}/defaults', 'getDefaultsSelections', $isSuper, $loadDefinition);

        $this->register('POST', '/{filter:\d+}/options/save', 'postSaveOptions', $isSuper, $optionDependencies);
        $this->register('POST', '/{filter:\d+}/defaults/save', 'postSaveDefaults', $isSuper, $defaultDependencies);
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
        $this->addTemplatePath($templateLoader, __DIR__ . '/Filters', 'filterDefinitions');
    }


    /**
     * @return \Schoolbox\Mapper|Mapper
     * @throws \InvalidArgumentException
     */
    protected function getFilterMapper()
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }
}
