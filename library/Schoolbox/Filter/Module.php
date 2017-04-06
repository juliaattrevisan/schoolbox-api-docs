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
        parent::__construct($injector, 'filters');
    }


    /**
     * @inheritdoc
     * @throws NotFound If invalid roleType
     * @throws \InvalidArgumentException
     * @throws ServiceNotFound
     */
    protected function registerRoutes()
    {
        $isSuper = new IsSuperuser();

        $loadFilters = function (){
            try {
                return [
                    'filters' => $this->getFilterMapper()->findAll(),
                ];
            } catch (MapperException $e) {
                throw new NotFound($e->getMessage());
            }
        };

        $loadDefinition = function (array $params){
            try {
                $filterInstance = $this->getFilterMapper()->findById($params['filter']);
                $filterFactoryFactory = new FactoryFactory($this->getMapperFactory());
                $filterFactory = $filterFactoryFactory->buildFilterFactoryForFilter($filterInstance->getClass());
                return [
                    'definition' => $filterFactory->buildDefinition($filterInstance),
                ];
            } catch (MapperException $e) {
                throw new NotFound($e->getMessage());
            }
        };

        $optionDependencies = function (array $params) use ($loadDefinition){
            return array_merge([
                'optionMapper' => $this->getMapperFactory()->get(Option\Mapper::class)
            ], $loadDefinition($params));
        };

        $defaultDependencies = function (array $params) use ($optionDependencies){
            return array_merge([
                'defaultMapper' => $this->getMapperFactory()->get(Defaults\Mapper::class)
            ], $optionDependencies($params));
        };



        $this->register('GET', '', 'getIndex', $isSuper, $loadFilters);
        $this->register('GET', '/{filter:\d+}/options', 'getAvailableOptions', $isSuper, $loadDefinition);
        $this->register('GET', '/{filter:\d+}/defaults', 'getDefaultsSelections', $isSuper, $loadDefinition);

        $this->register('POST', '/{filter:\d+}/options/save', 'postSaveOptions', $isSuper, $optionDependencies);
        $this->register('POST', '/{filter:\d+}/defaults/save', 'postSaveDefaults', $isSuper, $defaultDependencies);

//        $this->register('POST', '/convert/{courseFolderId:\d+}/{unitFolderId:\d+}', 'convertFolders', null, $preloadFolders);
//        $this->register('POST', '/{id}/unitspecs/new', 'newUnit', null, $preloadCourseSpec);
//        $this->register('POST', '/modify/{id}', 'updateCourse', null, $preloadCourseSpec);
//        $this->register('POST', '/unitspecs/{id}/delete', 'deleteUnitSpecification', null, $preloadUnitSpec);
//        $this->register('POST', '/unitspecs/{id}/sequence', 'saveAssessmentSequence', null, $preloadUnitSpec);
//        $this->register('DELETE', '/units/{id}', 'deleteUnitImplementation', null, $preloadUnitImpl);
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
