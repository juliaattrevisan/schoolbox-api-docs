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
use Schoolbox\Resource\Folder;
use Schoolbox\Mapper\Exception as MapperException;
use Twig_Error_Loader;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use \Component_DefinitionMapper;
use \Component_InstanceMapper;
use \Component_ConfigDefinitionMapper;
use \Component_ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Component\Homepage\Outline;

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

        $preloadImport = $this->buildPreloadImport();

        $preloadCourse = function (array $params)
        {
            return [
                'courseImpl' => $this->getCourseImplementationMapper()->findById($params['courseImplId'])
            ];
        };

        // courseSpecId + folderId required (even if 0), componentId optional
        $this->register('GET',    '/{courseSpecId:\d+}/folder/{folderId}{/componentId}', 'getImport',       $staffOnly, $preloadImport);
        $this->register('GET',    '/{courseImplId:\d+}/modify',                          'getModify',       $staffOnly, $preloadCourse);
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
     * @param IChainLoader $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/../templates');
    }


    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     * @throws \InvalidArgumentException
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class, [
                'unitSpecificationMapper'         => $this->getMapperFactory()->getLazy(UnitSpecificationMapper::class),
                'unitImplementationMapper'        => $this->getMapperFactory()->getLazy(UnitImplementationMapper::class),
                'folderMapper'                    => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'courseSpecificationMapper'       => $this->getMapperFactory()->getLazy(CourseSpecificationMapper::class),
                'courseImplementationMapper'      => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
                'componentInstanceMapper'         => $this->getMapperFactory()->getLazy(Component_InstanceMapper::class),
                'componentConfigDefinitionMapper' => $this->getMapperFactory()->getLazy(Component_ConfigDefinitionMapper::class),
                'componentConfigInstanceMapper'   => $this->getMapperFactory()->getLazy(Component_ConfigMapper::class),
                'errorMapper'                     => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'access'                          => $this->getMapperFactory()->getLazy(Access::class), // not a mapper, but has getInstance, so does work
                'importFactory'                   => $injector->lazyNew(ImportFactory::class),
                'replicator'                      => $injector->lazyNew(Replicator::class, [
                    'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
                    'quizMapper'            => $this->getMapperFactory()->getLazy(QuizMapper::class),
                    'rubricMapper'          => $this->getMapperFactory()->getLazy(RubricMapper::class),
                ]),
                'assessmentMapperProxy'           => $injector->lazyGet('assessmentDataSource'),
            ])
        );

    }

    /**
     * Return a function to preload objects for the import route
     *
     * @return callable
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Application\Exception\NotFound
     *
     */
    private function buildPreloadImport(): callable
    {
        return function ($params)
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

            try {
                $componentInstance = !empty($params['componentId'])
                    ? $this->getComponentInstanceMapper()->findById($params['componentId'])
                    : null;
            } catch (MapperException $e) {
                throw new NotFound("Component not found. id: {$params['componentId']}");
            }

            if($componentInstance){
                $definitionId = $componentInstance->getDefinitionId();
                $componentDefinition = $this->getComponentDefinitionMapper()->findById($definitionId);
                if ($componentDefinition->getClass() !== Outline\Controller::class) {
                    throw new NotFound("Not a Course-Outline component. id: {$params['componentId']}");
                }
                if(!$folder){
                    // No folder?
                    throw new NotFound('You cannot specify a component without a folder');
                }
                if((int)$componentInstance->getIdentifier() !== (int)$folder->getId()){
                    throw new NotFound('Component does not reside on specified folder');
                }

            }

            return [
                'courseSpec'  => $courseSpec,
                'targetClass' => $folder,
                'component'   => $componentInstance,
            ];
        };
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
     * @return Component_InstanceMapper|\Schoolbox\Mapper
     * @throws \InvalidArgumentException
     */
    protected function getComponentInstanceMapper()
    {
        return $this->getMapperFactory()->get(Component_InstanceMapper::class);
    }

    /**
     * @return Component_DefinitionMapper|\Schoolbox\Mapper
     * @throws \InvalidArgumentException
     */
    protected function getComponentDefinitionMapper()
    {
        return $this->getMapperFactory()->get(Component_DefinitionMapper::class);
    }
}
