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
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Assessment\Term\Model as TermModel;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

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
            'course'                 => '/course/{courseSpecId}',
            'courseWithTerms'        => '/course/{courseSpecId}/term/{termCode}',
            'courseWithTask'         => '/course/{courseSpecId}/task/{taskTypeId}',
            'courseWithTaskAndTerms' => '/course/{courseSpecId}/task/{taskTypeId}/term/{termCode}',

            // Per Class that uses a particular Course
            'folder'                 => '/course/{courseSpecId}/class/{folderId}',
            'folderWithTerms'        => '/course/{courseSpecId}/class/{folderId}/term/{termCode}',
            'folderWithTask'         => '/course/{courseSpecId}/class/{folderId}/task/{taskTypeId}',
            'folderWithTaskAndTerms' => '/course/{courseSpecId}/class/{folderId}/task/{taskTypeId}/term/{termCode}',
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
            $matcher, 'courseWith.*Terms',
            function($params) {
                return $this->courseDependencies($params);
            }
        );

        $this->addRoutePreload(
            $matcher, 'folderWith.*Terms',
            function($params) {
                return $this->folderDependencies($params);
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
     * Pass params when using course view
     *
     * @param array $params
     * @var int $params['courseSpecId']
     * @var int $params['termCode']
     *
     * @return array
     * @throws MapperException
     * @throws ApplicationException
     * @throws \InvalidArgumentException
     * @throws ServiceNotFound
     */
    private function courseDependencies(array $params)
    {
        try {
            $term = $this->findTerm($params['termCode']);
        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_DATE_FORMAT);
        }

        try {
            $courseSpecification = $this->getCourseSpecificationMapper()->findById($params['courseSpecId']);
        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        // Load CourseImpls imported from specified CourseSpec
        /** @var GenericCollection|CourseImplementation[] $courseImplementations */
        $courseImplementations = $this->getCourseImplementationMapper()->findAllByCourseSpecificationId($courseSpecification->getId())->indexBy('id');
        $courseSpecification->setCourseImplementations($courseImplementations);

        // Load UnitImpls inside CourseSpecs
        /** @var GenericCollection|UnitImplementation[] $unitImplementations */
        $unitImplementations = $this->getUnitImplementationMapper()->findAllByCourseId($courseImplementations->getColumnValues('id'))->indexBy('id');
        foreach($unitImplementations as $unitImplementation){
            $courseImplementation = $courseImplementations[$unitImplementation->getCourse()->getId()];
            $courseImplementation->getUnits()->setOffset($unitImplementation->getId(), $unitImplementation);
            $unitImplementation->setCourse($courseImplementation);
        }

        // Load all assessments in Unit Impls (regardless of due date - as we need them for navigation)
        /** @var GenericCollection|IAssessable[] $assessmentImpls */
        $assessmentImpls = $this->getAssessmentDataSource()->findAllByUnitImplementationId($unitImplementations->getColumnValues('id'))->indexBy('id');

        foreach($assessmentImpls as $assessmentImpl){
            $unitImplementation = $unitImplementations[$assessmentImpl->getContext()->getId()];
            $unitImplementation->getAssessables()->setOffset($assessmentImpl->getId(), $assessmentImpl);
            $assessmentImpl->setContext($unitImplementation);
        }

        // Get list of assessments due in specified term
        /** @var GenericCollection|IAssessable[] $assessmentsDueInTerm */
        $assessmentsDueInTerm = $assessmentImpls->filter(function(IAssessable $assessment) use ($term) {
            $dueAfterTermStart = $assessment->getDueAt()->gte($term->getStart());
            $dueBeforeTermEnd = $term->getActualFinish()->isNull() || $assessment->getDueAt()->lt($term->getActualFinish());
            return $dueAfterTermStart && $dueBeforeTermEnd;
        });

        // Should never have got to this page if there are no assessments due here
        if (0 === $assessmentsDueInTerm->count()){
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_SUBJECT_NO_TASKS);
        }

        // Load Participants
        $this->getParticipantDataSource()->loadParticipationIntoAssessmentCollection($assessmentsDueInTerm);
        foreach($assessmentsDueInTerm as $assessment){
            foreach ($assessment->getAllParticipants() as $participant) {
                foreach ($participant->getAllFeedbacks() as $feedback) {
                    $assessment->getFeedbacks()->setOffset($feedback->getId(), $feedback);
                }
                foreach ($participant->getAllResponses() as $response) {
                    $assessment->getResponses()->setOffset($response->getId(), $response);
                }
            }
        }

        /* Inject (this is used in twig)
         */
        $params['route'] = 'folder';

        return [
            'params'                       => $params,
            'courseSpecification'          => $courseSpecification,
            'unitImplementations'          => $unitImplementations,
            'term'                         => $term,
        ];
    }

    /**
     * Pass params when using folder view
     *
     * @param $params
     * @return array
     * @throws MapperException
     * @throws ApplicationException
     * @throws \InvalidArgumentException
     * @throws ServiceNotFound
     */
    private function folderDependencies($params)
    {
        $preload = $this->courseDependencies($params);

        // overlay folder selection
        try {
            $preload['selectedClassFolder'] = $this->getFolderMapper()->findById($params['folderId']);

        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_SUBJECT_NO_TASKS);
        }

        return $preload;
    }


    /**
     * @param string $termCode
     * @return TermModel
     * @throws MapperException
     */
    private function findTerm($termCode)
    {
        try {
            return $this->getTermMapper()->findOneByColumn('code', $termCode);

        } catch (\Exception $e) {
            throw new MapperException('Invalid date range.');
        }
    }

    /**
     * Template locations for this module
     *
     * {@inheritdoc}
     * @throws \Twig_Error_Loader
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Set up controllers
     *
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
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
     * @return object|Participant\DataSource
     * @throws ServiceNotFound
     */
    protected function getParticipantDataSource()
    {
        return $this->getInjector()->get('participantDataSource');
    }
}
