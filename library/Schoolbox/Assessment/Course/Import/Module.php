<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\Unit;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;
use Twig_Error_Loader;
use Twig_Loader_Chain;

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
            return [
                'courseSpec'  => $this->getCourseSpec($params['courseSpecId']),
                'targetClass' => $this->getFolder($params['classCode']),
                'componentId' => $params['componentId']
            ];
        };

        $preloadCourse = function (array $params)
        {
            return [
                'courseImplementation' => $this->getCourseImplementationMapperProxy()->findById($params['courseImplId'])
            ];
        };

        $this->register('GET', '/{courseSpecId:\d+}/class/{classCode}/{componentId}', 'getImportToComponentForm', $staffOnly, $preloadImport);
        $this->register('GET', '/{courseSpecId:\d+}/class{/classCode}', 'getImportToClassForm', $staffOnly, $preloadImport);
        $this->register('GET', '/{courseImplId:\d+}/modify', 'importModify',  $staffOnly, $preloadCourse);

        $this->register('GET',  '/classes',   'searchClasses', $staffOnly);
        $this->register('POST', '/classes',   'postClasses',   $staffOnly);
        $this->register('POST', '/ownership', 'postOwnership', $staffOnly);
        $this->register('POST', '/units',     'postUnits',     $staffOnly);
        $this->register('POST', '/tasks',     'postTasks',     $staffOnly);
        $this->register('POST', '/delete',    'importDelete',  $staffOnly);
        $this->register('POST', '/restore',   'restoreCourse', $staffOnly);
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
                'setUnitSpecificationMapperProxy'   => $injector->lazyGet('unitSpecificationDataSource'),
                'setUnitImplementationMapperProxy'   => $injector->lazyGet('unitImplementationDataSource'),
                'setFolderMapper'                   => $this->getFolderMapper(),
                'setCourseSpecificationMapperProxy' => $this->getCourseSpecificationMapperProxy(),
                'setCourseImplementationMapperProxy' => $this->getCourseImplementationMapperProxy(),
                'setUserAccessMapper'               => $this->getMapperFactory()->getLazy(UserAccessMapper::class), // TODO should not need both Access and UserAccessMapper...
                'setAccess'                         => $this->getMapperFactory()->getLazy(Access::class), // not a mapper, but has getInstance, so does work
                'setUserMapper'                     => $this->getMapperFactory()->getLazy(UserMapper::class),
                'setAssessmentMapper'               => $injector->lazyGet('assessmentDataSource'),
                'setCourseImplementationFactory'    => $injector->lazyNew(Course\ImplementationFactory::class, [
                    'unitImplementationFactory' => $injector->lazyNew(Unit\ImplementationFactory::class)
                ])
            ])
        );

    }

    /**
     * @return Course\ImplementationMapperProxy
     */
    protected function getCourseImplementationMapperProxy()
    {
        return $this->injector->get('courseImplementationDataSource');
    }

    /**
     * @return Course\SpecificationMapperProxy
     */
    protected function getCourseSpecificationMapperProxy()
    {
        return $this->injector->get('courseSpecificationDataSource');
    }

    /**
     * @return Folder\Mapper
     */
    protected function getFolderMapper()
    {
        return $this->injector->get('mapperFactory')->get(Folder\Mapper::class);
    }

    /**
     * @param int $id
     * @return Course\Specification
     */
    protected function getCourseSpec($id)
    {
        try {
            return $this->getCourseSpecificationMapperProxy()->findById($id);

        } catch (\Schoolbox\Mapper\Exception $e) {
            // null is ok
            return null;
        }
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
            } catch (\Schoolbox\Mapper\Exception $e) {
                if ($codeOrId) {
                    $targetClass = $this->getFolderMapper()->findById($codeOrId);
                } else {
                    $targetClass = null;
                }
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            // null is ok
            $targetClass = null;
        }

        return $targetClass;
    }
}
