<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Component_ConfigDefinitionMapper;
use Component_ConfigMapper;
use Component_DefinitionMapper;
use Component_InstanceMapper;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\Component\Homepage\Outline;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Course;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Twig\Error\LoaderError;

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

        $preloadCourse = function (array $params) {
            return [
                'courseImpl' => $this->getCourseImplementationMapper()->findById($params['courseImplId'])
            ];
        };

        // courseSpecId + folderId required (even if 0), componentId optional
        $this->register('GET', '/{courseSpecId:\d+}/folder/{folderId}{/componentId}', 'getImport', $staffOnly, $preloadImport);
        $this->register('GET', '/{courseImplId:\d+}/modify', 'getModify', $staffOnly, $preloadCourse);
        $this->register('GET', '/classes', 'searchClasses', $staffOnly);
        $this->register('GET', '/management/{folderId}', 'getManagement', $staffOnly);

        $this->register('POST', '/classes', 'postClasses', $staffOnly);
        $this->register('POST', '/ownership', 'postOwnership', $staffOnly);
        $this->register('POST', '/units', 'postUnits', $staffOnly);
        $this->register('POST', '/activities', 'postAssessments', $staffOnly);
        $this->register('DELETE', '/{courseImplId:\d+}', 'importDelete', $staffOnly, $preloadCourse);
        $this->register('POST', '/{courseImplId:\d+}/restore', 'restoreCourse', $staffOnly, $preloadCourse);
    }


    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/../templates');
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        $replicator = new Replicator(
            $this->injector->get('assessmentDataSource'),
            $this->getMapperFactory()->get(QuizMapper::class),
            $this->getMapperFactory()->get(RubricDataSource::class),
        );
        $importFactory = new ImportFactory(
            $this->injector->get('assessmentDataSource'),
            $replicator,
            $this->getMapperFactory()->get(CourseImplementationMapper::class),
            $this->getMapperFactory()->get(UnitImplementationMapper::class),
            $this->getMapperFactory()->get(Component_InstanceMapper::class),
            $this->getMapperFactory()->get(Component_ConfigDefinitionMapper::class),
            $this->getMapperFactory()->get(Component_ConfigMapper::class),
        );
        return new Controller(
            $this->getMapperFactory()->get(UnitSpecificationMapper::class),
            $this->getMapperFactory()->get(UnitImplementationMapper::class),
            $this->getMapperFactory()->get(FolderMapper::class),
            $this->getMapperFactory()->get(CourseSpecificationMapper::class),
            $this->getMapperFactory()->get(CourseImplementationMapper::class),
            $this->getMapperFactory()->get(Component_InstanceMapper::class),
            // not a mapper, but has getInstance, so does work
            $this->getMapperFactory()->get(Access::class),
            $importFactory,
            $this->injector->get('assessmentDataSource')
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
        return function ($params) {
            try {
                $courseSpec = $params['courseSpecId']
                    ? $this->getCourseSpecificationMapper()->findById($params['courseSpecId'])
                    : null;
            } catch (MapperException $e) {
                throw new NotFound("Course Spec not found. id: {$params['courseSpecId']}");
            }

            try {
                $folder = $params['folderId']
                    ? $this->getFolderMapper()->findById($params['folderId'])
                    : null;
            } catch (MapperException $e) {
                throw new NotFound("Folder not found. id: {$params['folderId']}");
            }

            try {
                $componentInstance = !empty($params['componentId'])
                    ? $this->getComponentInstanceMapper()->findById($params['componentId'])
                    : null;
            } catch (MapperException $e) {
                throw new NotFound("Component not found. id: {$params['componentId']}");
            }

            if ($componentInstance) {
                $definitionId = $componentInstance->getDefinitionId();
                $componentDefinition = $this->getComponentDefinitionMapper()->findById($definitionId);
                if ($componentDefinition->getClass() !== Outline\Controller::class) {
                    throw new NotFound("Not a Course-Outline component. id: {$params['componentId']}");
                }
                if (!$folder) {
                    // No folder?
                    throw new NotFound('You cannot specify a component without a folder');
                }
                if ((int)$componentInstance->getIdentifier() !== (int)$folder->getId()) {
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
