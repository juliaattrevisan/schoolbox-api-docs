<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Application\Exception\Exception as ApplicationException;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;

class Module extends BaseModule
{

    /** @var string */
    protected $basename = 'learningAnalytics';


    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $get = [
            // For a Course - aggregating all Classes which have imported Course
            'course'                          => '/course/{courseSpecId}',
            'courseWithTerm'                  => '/course/{courseSpecId}/term/{termCode}',
            'courseWithSubmissionType'        => '/course/{courseSpecId}/submissionType/{submissionTypeId}',
            'courseWithSubmissionTypeAndTerm' => '/course/{courseSpecId}/submissionType/{submissionTypeId}/term/{termCode}',

            // Per Class that uses a particular Course
            'folder'                          => '/course/{courseSpecId}/class/{folderId}',
            'folderWithTerm'                  => '/course/{courseSpecId}/class/{folderId}/term/{termCode}',
            'folderWithSubmissionType'        => '/course/{courseSpecId}/class/{folderId}/submissionType/{submissionTypeId}',
            'folderWithSubmissionTypeAndTerm' => '/course/{courseSpecId}/class/{folderId}/submissionType/{submissionTypeId}/term/{termCode}',
        ];

        foreach ($get as $method => $route) {
            $this->addGetRoute($router, $method, $basePath, $route);
        }
    }


    /**
     * Inject params into controller
     *
     * {@inheritdoc}
     * @throws Mapper\Exception
     * @throws ApplicationException
     * @throws \InvalidArgumentException
     * @throws ServiceNotFound
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        /* All routes that include the term will be processed, those without a term
         * will receive scalars and redirect to current term equivalent
         */
        $this->addRoutePreload(
            $matcher, '.*With.*Term',
            function ($params) {
                return $this->loadDependencies($params);
            }
        );
    }


    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, '.*', new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
                )
            );
    }


    /**
     * Loads model graph.
     *
     * @param array $params
     * @var int     $params ['courseSpecId']
     * @var int     $params ['termCode']
     * @var int     $params ['folderId']                Optional
     * @var int     $params ['submissionType']          Optional
     *
     * @return array
     * @var string[]                               return['params']
     * @var CourseSpecification                    return['courseSpecification']
     * @var GenericCollection|UnitImplementation[] return['unitImplementations']
     * @var Term                                   return['term']
     * @var null|Folder                            return['selectedClassFolder']
     * @throws ApplicationException
     * @throws MapperException
     * @throws Mapper\DefinitionException
     * @throws ServiceNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function loadDependencies(array $params)
    {
        try {
            $term = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_DATE_FORMAT);
        }

        try {
            $courseSpecification = $this->getCourseSpecificationMapper()->findById($params['courseSpecId']);
        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        $submissionType = null;
        if (isset($params['submissionTypeId'])) {
            try {
                $submissionType = $this->getWorkTypeMapper()->findById($params['submissionTypeId']);
            } catch (MapperException $e) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
            }
        }

        $classFolder = null;
        if (isset($params['folderId'])) {
            try {
                $classFolder = $this->getFolderMapper()->findById($params['folderId']);
            } catch (MapperException $e) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
            }
        }

        // Load CourseImpls imported from specified CourseSpec
        /** @var GenericCollection|CourseImplementation[] $courseImplementations */
        $courseImplementations = $this->getCourseImplementationMapper()->findByColumnList([
            'course_specification_id' => $courseSpecification->getId(),
        ])->indexBy('id');
        $courseSpecification->setCourseImplementations($courseImplementations);

        // Load UnitImpls (filtering by class if a class is selected) inside CourseImpls,

        $columnList = [
            'course_implementation_id' => $courseImplementations->getColumnValues('id'),
        ];
        if ($classFolder) {
            $columnList['c.folder_id'] = $classFolder->getId();
        }

        /** @var GenericCollection|UnitImplementation[] $unitImplementations */
        $unitImplementations = $this->getUnitImplementationMapper()->findByColumnList($columnList)->indexBy('id');
        foreach ($unitImplementations as $unitImplementation) {
            $courseImplementation = $courseImplementations[$unitImplementation->getCourse()->getId()];
            $courseImplementation->getUnits()->setOffset($unitImplementation->getId(), $unitImplementation);
            $unitImplementation->setCourse($courseImplementation);
        }

        // Load Assessments in Unit Impls (regardless of due date - as we need them for navigation)
        /** @var GenericCollection|IAssessable[] $assessmentImpls */
        $assessmentImpls = $this->getAssessmentDataSource()->findAllByUnitImplementationIds($unitImplementations->getColumnValues('id'))->indexBy('id');
        foreach ($assessmentImpls as $assessmentImpl) {
            $unitImplementation = $unitImplementations[$assessmentImpl->getContext()->getId()];
            $unitImplementation->getAssessables()->setOffset($assessmentImpl->getId(), $assessmentImpl);
            $assessmentImpl->setContext($unitImplementation);
        }

        // Get the assessments that are due in currently selected term, and match the class if a class filter is applied
        /** @var GenericCollection|IAssessable[] $dueAssessments */
        $dueAssessments = $assessmentImpls->filter(function (IAssessable $assessment) use ($term, $classFolder) {

            // If filtering to class, ensure assessment belongs to class
            /** @param Folder $classFolder */
            if ($classFolder && $classFolder->getId() !== $assessment->getBaseContext()->getFolder()->getId()){
                return false;
            }

            // Ensure Assessment is due in term
            $dueAfterTermStart = $assessment->getDueAt()->gte($term->getStart());
            $dueBeforeTermEnd  = $term->getActualFinish()->isNull() || $assessment->getDueAt()->lt($term->getActualFinish());
            return $dueAfterTermStart && $dueBeforeTermEnd;
        });

        // Should never have got to this page the course was not imported
        if (0 === $unitImplementations->count()) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_NO_ANALYTICS_DATA);
        }

        /**
         * UnitImpls that contain assessments due in selected Term
         *
         * @var GenericCollection|UnitImplementation[] $unitImplsInTerm
         */
        $unitImplsInTerm = $this->getUnitImplementationMapper()->createCollection();

        // Load Participants into relevant Assessments
        $dueAssessments = $this->getParticipantDataSource()->loadParticipationIntoAssessmentCollection($dueAssessments, false);
        foreach ($dueAssessments as $assessment) {
            $unitImplsInTerm->setOffset($assessment->getContext()->getId(), $assessment->getBaseContext());
        }


        return [
            'courseSpecification' => $courseSpecification,
            'unitImplsInTerm'     => $unitImplsInTerm,
            'dueAssessments'      => $dueAssessments,
            'term'                => $term,
            'classFolder'         => $classFolder,
            'submissionType'      => $submissionType,
        ];
    }


    /**
     * Template locations for this module
     *
     * {@inheritdoc}
     * @throws \Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * Set up controllers
     *
     * {@inheritdoc}
     * @throws \InvalidArgumentException
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'termMapper'           => $this->getMapperFactory()->getLazy(TermMapper::class),
                'folderMapper'         => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'workTypeMapper' => $this->getMapperFactory()->getLazy(WorkTypeMapper::class),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return object|Access
     * @throws \InvalidArgumentException
     */
    protected function getAccess()
    {
        return $this->getMapperFactory()->get(Access::class);
    }


    /**
     * @return Mapper|FolderMapper
     * @throws \InvalidArgumentException
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }


    /**
     * @return object|MapperProxy
     * @throws ServiceNotFound
     */
    protected function getAssessmentDataSource()
    {
        return $this->getInjector()->get('assessmentDataSource');
    }


    /**
     * @return Mapper|CourseImplementationMapper
     * @throws \InvalidArgumentException
     */
    protected function getCourseImplementationMapper()
    {
        return $this->getMapperFactory()->get(CourseImplementationMapper::class);
    }


    /**
     * @return Mapper|CourseSpecificationMapper
     * @throws \InvalidArgumentException
     */
    protected function getCourseSpecificationMapper()
    {
        return $this->getMapperFactory()->get(CourseSpecificationMapper::class);
    }


    /**
     * @return Mapper|UnitImplementationMapper
     * @throws \InvalidArgumentException
     */
    protected function getUnitImplementationMapper()
    {
        return $this->getMapperFactory()->get(UnitImplementationMapper::class);
    }


    /**
     * @return Mapper|TermMapper
     * @throws \InvalidArgumentException
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }


    /**
     * @return Mapper|WorkTypeMapper
     * @throws \InvalidArgumentException
     */
    protected function getWorkTypeMapper()
    {
        return $this->getMapperFactory()->get(WorkTypeMapper::class);
    }


    /**
     * @return object|Participant\DataSource
     * @throws ServiceNotFound
     */
    protected function getParticipantDataSource()
    {
        return $this->getInjector()->get('participantDataSource');
    }
}
