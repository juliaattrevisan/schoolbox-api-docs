<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Aura\Di\ContainerInterface;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\Unit;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Twig_Error_Loader;
use Twig_Loader_Chain;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use \Component_InstanceMapper;

class Module extends \Alaress\Module\Module
{
    /** @var string */
    protected $basename = 'courseImport';

    /**
     * @return void
     */
    protected function registerRoutes()
    {
        $staffOnly = $this->getAuthRule('isStaffOrSuper');

        $preloadImport = function ($params)
        {
            try{
                $courseSpec = $this->getCourseSpecificationMapper()->findById($params['courseSpecId']);
            } catch (MapperException $e){
                // Null is ok, use will be able to select from dropdown
                $courseSpec = null;
            }


            return [
                'courseSpec'  => $courseSpec,
                'targetClass' => $this->getFolder($params['classCode']),
                'componentId' => $params['componentId']
            ];
        };

        $preloadCourse = function (array $params)
        {
            return [
                'courseImpl' => $this->getCourseImplementationMapper()->findById($params['courseImplId'])
            ];
        };

        $this->register('GET',    '/{courseSpecId:\d+}/class/{classCode}/{componentId}', 'getImportToComponentForm', $staffOnly, $preloadImport);
        $this->register('GET',    '/{courseSpecId:\d+}/class{/classCode}',               'getImportToClassForm',     $staffOnly, $preloadImport);
        $this->register('GET',    '/{courseImplId:\d+}/modify',                          'importModify',             $staffOnly, $preloadCourse);
        $this->register('GET',    '/classes',                                            'searchClasses',            $staffOnly);
        $this->register('GET',    '/management/{folderId}',                              'getManagement',            $staffOnly);

        $this->register('POST',   '/classes',                                            'postClasses',              $staffOnly);
        $this->register('POST',   '/ownership',                                          'postOwnership',            $staffOnly);
        $this->register('POST',   '/units',                                              'postUnits',                $staffOnly);
        $this->register('POST',   '/activities',                                         'postAssessments',          $staffOnly);
        $this->register('DELETE', '/{courseImplId:\d+}',                                 'importDelete',             $staffOnly, $preloadCourse);
        $this->register('POST',   '/{courseImplId:\d+}/restore',                         'restoreCourse',            $staffOnly, $preloadCourse);
    }

    /**
     * @param Twig_Loader_Chain $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/../templates');
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class, [], [
                'setUnitSpecificationMapper'        => $this->getMapperFactory()->getLazy(UnitSpecificationMapper::class),
                'setUnitImplementationMapper'       => $this->getMapperFactory()->getLazy(UnitImplementationMapper::class),
                'setFolderMapper'                   => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'setCourseSpecificationMapper'      => $this->getMapperFactory()->getLazy(CourseSpecificationMapper::class),
                'setCourseImplementationMapper'     => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
                'setComponentInstanceMapper'        => $this->getMapperFactory()->getLazy(Component_InstanceMapper::class),
                'setUserAccessMapper'               => $this->getMapperFactory()->getLazy(UserAccessMapper::class), // TODO should not need both Access and UserAccessMapper...
                'setAccess'                         => $this->getMapperFactory()->getLazy(Access::class), // not a mapper, but has getInstance, so does work
                'setUserMapper'                     => $this->getMapperFactory()->getLazy(UserMapper::class),
                'setImportFactory'                  => $injector->lazyNew(ImportFactory::class),
                'setAssessmentMapperProxy'          => $injector->lazyGet('assessmentDataSource'),
                'setCourseImplementationFactory'    => $injector->lazyNew(Course\ImplementationFactory::class, [
                    'unitImplementationFactory' => $injector->lazyNew(Unit\ImplementationFactory::class)
                ])
            ])
        );

    }

    /**
     * @return CourseSpecificationMapper
     */
    protected function getCourseSpecificationMapper()
    {
        return $this->injector->get('mapperFactory')->get(CourseSpecificationMapper::class);
    }

    /**
     * @return CourseImplementationMapper
     */
    protected function getCourseImplementationMapper()
    {
        return $this->injector->get('mapperFactory')->get(CourseImplementationMapper::class);
    }

    /**
     * @return Folder\Mapper
     */
    protected function getFolderMapper()
    {
        return $this->injector->get('mapperFactory')->get(Folder\Mapper::class);
    }


    /**
     * @param string $codeOrId
     * @return Folder\Model
     */
    protected function getFolder($codeOrId)
    {
        try {
            try {
                $targetClass = $this->getFolderMapper()->findByCode($codeOrId);
            } catch (MapperException $e) {
                if ($codeOrId) {
                    $targetClass = $this->getFolderMapper()->findById($codeOrId);
                } else {
                    $targetClass = null;
                }
            }
        } catch (MapperException $e) {
            // null is ok
            $targetClass = null;
        }

        return $targetClass;
    }
}
