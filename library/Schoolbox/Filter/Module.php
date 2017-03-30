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
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Role;
use \Twig_Loader_Chain;
use Schoolbox\Filter\Mapper as FilterMapper;


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
     */
    protected function registerRoutes()
    {
        $isSuper = new IsSuperuser();

        $loadFilter = function (array $params){
            try {
                return ['filter' => $this->getFilterMapper()->findById($params['filter'])];
            } catch (MapperException $e) {
                throw new NotFound($e->getMessage());
            }
        };

        $this->register('GET', '', 'getIndex', $isSuper);
        $this->register('GET', '/{filter:\d+}/options', 'getAdminOptions', $isSuper, $loadFilter);
        $this->register('GET', '/{filter:\d+}/defaults', 'getAdminDefaults', $isSuper, $loadFilter);

        $this->register('POST', '/{filter:\d+}/options/save', 'postSaveOptions', $isSuper, $loadFilter);
        $this->register('POST', '/{filter:\d+}/defaults/save', 'postSaveDefaults', $isSuper, $loadFilter);

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
    }


    /**
     * @return \Schoolbox\Mapper|FilterMapper
     * @throws \InvalidArgumentException
     */
    protected function getFilterMapper()
    {
        return $this->getMapperFactory()->get(FilterMapper::class);
    }
}
