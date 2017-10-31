<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Aura\Di\ContainerInterface;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Learning\Course;
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
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use \Component_DefinitionMapper;
use \Component_InstanceMapper;
use \Component_ConfigDefinitionMapper;
use \Component_ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Assessment\Replicator;

class Module extends \Alaress\Module\Module
{
    /** @var string */
    protected $basename = 'courseImport';


    /**
     * @return void
     * @throws \Schoolbox\Application\Exception\NotFound
     * @throws \InvalidArgumentException
     *
     */
    protected function registerRoutes(): void
    {
        $staffOnly = $this->getAuthRule('isStaffOrSuper');

        $preloadImport = function ($params)
        {
            try{
                $courseSpec = $params['courseSpecId']
                    ? $this->getCourseSpecificationMapper()->findById($params['courseSpecId'])
                    : null;
            } catch (MapperException $e){
                throw new NotFound("Course Spec not found. id: {$params['courseSpecId']}");
            }

            try{
                $folder = $params['folderId']
                    ? $this->getFolderMapper()->findById($params['folderId'])
                    : null;
            } catch (MapperException $e){
                throw new NotFound("Folder not found. id: {$params['folderId']}");
            }

            return [
                'courseSpec'  => $courseSpec,
                'targetClass' => $folder,
                'componentId' => isset($params['componentId']) ? (int)$params['componentId'] : null,
            ];
        };

        $preloadCourse = function (array $params)
        {
            return [
                'courseImpl' => $this->getCourseImplementationMapper()->findById($params['courseImplId'])
            ];
        };

        // courseSpecId + folderId required (even if 0), componentId optional
        $this->register('GET',    '/{courseSpecId:\d+}/folder/{folderId}{/componentId}', 'getImport',       $staffOnly, $preloadImport);
        $this->register('GET',    '/{courseImplId:\d+}/modify',                          'importModify',    $staffOnly, $preloadCourse);
        $this->register('GET',    '/classes',                                            'searchClasses',   $staffOnly);
        $this->register('GET',    '/management/{folderId}',                              'getManagement',   $staffOnly);

        $this->register('POST',   '/classes',                                            'postClasses',     $staffOnly);
        $this->register('POST',   '/ownership',                                          'postOwnership',   $staffOnly);
        $this->register('POST',   '/units',                                              'postUnits',       $staffOnly);
        $this->register('POST',   '/activities',                                         'postAssessments', $staffOnly);
        $this->register('DELETE', '/{courseImplId:\d+}',                                 'importDelete',    $staffOnly, $preloadCourse);
        $this->register('POST',   '/{courseImplId:\d+}/restore',                         'restoreCourse',   $staffOnly, $preloadCourse);
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
                'setUnitSpecificationMapper'         => $this->getMapperFactory()->getLazy(UnitSpecificationMapper::class),
                'setUnitImplementationMapper'        => $this->getMapperFactory()->getLazy(UnitImplementationMapper::class),
                'setFolderMapper'                    => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'setCourseSpecificationMapper'       => $this->getMapperFactory()->getLazy(CourseSpecificationMapper::class),
                'setCourseImplementationMapper'      => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
                'setComponentDefinitionMapper'       => $this->getMapperFactory()->getLazy(Component_DefinitionMapper::class),
                'setComponentInstanceMapper'         => $this->getMapperFactory()->getLazy(Component_InstanceMapper::class),
                'setComponentConfigDefinitionMapper' => $this->getMapperFactory()->getLazy(Component_ConfigDefinitionMapper::class),
                'setComponentConfigInstanceMapper'   => $this->getMapperFactory()->getLazy(Component_ConfigMapper::class),
                'setErrorMapper'                     => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'setUserAccessMapper'                => $this->getMapperFactory()->getLazy(UserAccessMapper::class), // TODO should not need both Access and UserAccessMapper...
                'setAccess'                          => $this->getMapperFactory()->getLazy(Access::class), // not a mapper, but has getInstance, so does work
                'setUserMapper'                      => $this->getMapperFactory()->getLazy(UserMapper::class),
                'setImportFactory'                   => $injector->lazyNew(ImportFactory::class),
                'setReplicator'                      => $injector->lazyNew(Replicator::class, [
                    'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
                    'quizMapper'            => $this->getMapperFactory()->getLazy(QuizMapper::class),
                    'rubricMapper'          => $this->getMapperFactory()->getLazy(RubricMapper::class),
                ]),
                'setAssessmentMapper'                => $this->getMapperFactory()->getLazy(AssessmentMapper::class),
                'setAssessmentMapperProxy'           => $injector->lazyGet('assessmentDataSource'),
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
}
