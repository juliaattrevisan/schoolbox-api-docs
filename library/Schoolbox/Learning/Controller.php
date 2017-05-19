<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Statistics;
use Schoolbox\Learning\Classes\ClassList;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;
use Timetable_ColourMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use \Alaress_Date_Format;
use Schoolbox\Filter;
use Schoolbox\Filter\Option as FilterOption;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\Mapper;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;

class Controller extends Base
{
    /**
     * @var TermMapper
     */
    private $termMapper;

    /**
     * @var FolderMapper
     */
    private $folderMapper;
    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;
    /**
     * @var RubricMapper

    /** @var Timetable_ColourMapper */
    private $timetableColourMapper;

    /** @var CourseImplementationMapper */
    private $courseImplementationMapper;

    /** @var CourseSpecificationMapper */
    private $courseSpecificationMapper;

    /** @var Filter\Mapper */
    private $filterMapper;


    /** @var FilterSelection\Mapper */
    private $filterSelectionMapper;


    /** @var AssessmentFilters\IdFinder */
    private $assessmentIdFinder;


    /**
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param FolderMapper               $folderMapper
     * @param TermMapper                 $termMapper
     * @param Timetable_ColourMapper     $timetableColourMapper
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param CourseSpecificationMapper  $courseSpecificationMapper
     * @param FilterSelection\Mapper     $filterSelectionMapper
     * @param Filter\Mapper              $filterMapper
     * @param AssessmentFilters\IdFinder $assessmentIdFinder
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        FolderMapper $folderMapper,
        TermMapper $termMapper,
        Timetable_ColourMapper $timetableColourMapper,
        CourseImplementationMapper $courseImplementationMapper,
        CourseSpecificationMapper $courseSpecificationMapper,
        FilterSelection\Mapper $filterSelectionMapper,
        Filter\Mapper $filterMapper,
        AssessmentFilters\IdFinder $assessmentIdFinder
    ) {
        $this->assessmentMapperProxy      = $assessmentMapperProxy;
        $this->termMapper                 = $termMapper;
        $this->folderMapper               = $folderMapper;
        $this->timetableColourMapper      = $timetableColourMapper;
        $this->courseImplementationMapper = $courseImplementationMapper;
        $this->courseSpecificationMapper  = $courseSpecificationMapper;
        $this->filterSelectionMapper      = $filterSelectionMapper;
        $this->filterMapper               = $filterMapper;
        $this->assessmentIdFinder         = $assessmentIdFinder;
    }


    /**
     * Redirects to current user
     *
     * @api   GET /learning/pending
     * @level 165
     * @throws RouteNotFound
     */
    public function getPendingAssessments()
    {
        // Is current user not SuperUser or Staff ?
        if (!($this->getUser()->isSuperuser() || $this->getUser()->getRole()->isStaff())) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learningUpcoming.getUpcomingForStudent', [
                'studentId' => $this->getUser()->getId(),
            ]));
        }

        // If no staff explicitly requested, redirect to current user
        $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getPendingAssessmentsForUser', [
            'userId' => $this->getUser()->getId(),
            'viewee' => $this->getUser(),
        ]));
    }


    /**
     * Displays a blank page with collapsed filter panel
     *
     * @api   GET /learning/pending/:userId
     * @level 165
     * @param User                             $viewee The user whose pending assessments we are viewing.
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     * @var mixed[][] $_GET. Comma-separated keys, indexed by filter.id. Eg '1=&2=weighted&3=1,2,3' (1 => [], 2 => [weighted], 3 => [1,2,3])
     * @throws RouteNotFound
     * @throws ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws Mapper\Exception
     * @throws \ReflectionException If unable to find the class for a filter registered on this page
     * @throws \DomainException If filter factoryFactory has not defined the entity it is filtering
     */
    public function getPendingAssessmentsForUser(User $viewee, AssessmentFilters\FactoryFactory $filterFactoryFactory)
    {
        $page      = FilterOption\Model::PAGE_PENDING_ASSESSMENTS;
        $route     = $this->getRouter()->generate('learning.postPendingAssessmentsForUser', ['userId' => $viewee->getId()]);
        $queryArgs = $this->getRequest()->query->getArrayCopy();

        $filterPanel = $filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, $queryArgs);

        $title = 'Pending Assessments';
        if($viewee->getId() !== $this->getUser()->getId()){
            $title .= " for {$viewee->getFullName()}";
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learning/pendingAssessmentsOuter.twig', [
                'viewee'      => $viewee,
                'filterPanel' => $filterPanel,
            ])->setTab('submission')->setTitle($title)
        );
    }


    /**
     * Displays a list of Assessments that require marking, and Assessments currently unsubmitted
     *
     * @api   GET /learning/pending/:userId
     * @level 165
     * @param User                             $viewee The user whose pending assessments we are viewing. TODO not used, but will be when GET + POST routes merge
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     * @var  string[][]                        $_POST  ['filter_selection'] Indexed by filter.id, then concrete key
     * @throws \LogicException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws ServiceNotFound
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function postPendingAssessmentsForUser(User $viewee, AssessmentFilters\FactoryFactory $filterFactoryFactory)
    {
        try {
            $postFilterSelections = $this->request->post['filter_selection'] ?: [];

            $content = $this->generatePendingAssessmentReport($viewee, $filterFactoryFactory, $postFilterSelections);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $content,
            ]));

        } catch (\Exception $e) {
            // TODO - can remove this block when we abandon PHP 5 support
            $this->returnJsonError($e);
        } catch (\Throwable $e) {
            $this->returnJsonError($e);
        }
    }


    /**
     * Generate a pending assessment report as HTML string
     *
     * @param User $viewee
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     * @param string[][]                       $postFilterSelections - Indexed by filter.id, then concrete key
     * @return string
     * @throws \InvalidArgumentException
     * @throws \DomainException
     * @throws \LogicException
     * @throws \ReflectionException If the specified class does not exist
     * @throws Filter\BrokenFilterException If a injected filter is unable to be processed
     */
    protected function generatePendingAssessmentReport(
        User $viewee,
        AssessmentFilters\FactoryFactory $filterFactoryFactory,
        array $postFilterSelections
    ) {
        $page          = FilterOption\Model::PAGE_PENDING_ASSESSMENTS;
        $keyConverters = $filterFactoryFactory->buildKeyConverters($page);
        $codec         = new Filter\Codec();

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelectionModels($this->filterSelectionMapper, $keyConverters, $this->getUser(), $page, $postFilterSelections);

        // Don't save selections if viewing someone else's pending assessments
        // TODO Add ability to save per viewee. SA-3444
        if($viewee->getId() === $this->getUser()->getId()){
            $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        // Convert Selection models to Filters
        $selectionTransformer = new AssessmentFilters\Transformer($keyConverters, $selectionsPerFilter);
        $filters              = $selectionTransformer->buildFiltersDto();

        // Explicitly inject filter to ensure we don't get assessments that live directly in UnitSpecs,
        // regardless of any filter selections sent by user
        $unitSpecFilter = $this->filterMapper->findByClass(AssessmentFilters\NullableContext\UnitSpec\Definition::class)->first();
        $filters->setSelections($unitSpecFilter, new Collection());

        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters->getByClass());

        // Fetch assessments & participants
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $assessments = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments);

        $toChaseLimit = \Alaress_Date_Format::now()->addDays(1);

        /**
         * Indexed by assessment.id
         *
         * @var Statistics[]|GenericCollection $assessmentStatistics
         */
        $assessmentStatistics = new GenericCollection(Statistics::class);

        /** @var Collection|IAssessable[] $assessmentsToMark */
        $assessmentsToMark = $this->assessmentMapperProxy->createCollection();
        foreach ($assessments as $assessment) {
            $assessmentStatistics[$assessment->getId()] = new Statistics($assessment);
            if ($assessmentStatistics[$assessment->getId()]->getCountFeedbackRequired()) {
                $assessmentsToMark->add($assessment);
            }
        }
        $assessmentsToMark->sortBy('dueAt', 'asc');

        /** @var IAssessable[] $assessmentsToMark */
        $assessmentsToChase = $this->assessmentMapperProxy->createCollection();

        foreach ($assessments as $assessment) {
            if ($assessment->getDueAt()->lt($toChaseLimit) && $assessmentStatistics[$assessment->getId()]->getCountResponseRequired()) {
                $assessmentsToChase->add($assessment);
            }
        }
        $assessmentsToChase->sortBy('dueAt', 'asc');

        return $this->buildUnthemedHtmlOutput('@learning/pendingAssessmentsContent.twig', [
            'statistics'         => $assessmentStatistics,
            'assessmentsToMark'  => $assessmentsToMark,
            'assessmentsToChase' => $assessmentsToChase,
        ])->getAsString();
    }


    /**
     * Returns a JSON representation of an exception suitable for AJAX HTML alert divs
     *
     * @param \Throwable $e
     */
    protected function returnJsonError($e)
    {
        $this->getResponse()->content->set($this->buildJsonOutput([
            'status'  => 500,
            'code'    => $e->getCode(),
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ]));
    }


    /**
     * Displays a list of classes for the current logged in user
     *
     * If the term includes now (ie is current) then we use current enrollment.
     * Else term is historical, so we display classes where a student has submitted responses or received feedback
     *
     * @api   GET /learning/classes{/term}
     * @level 165
     *
     * @param Term $term
     * @throws ServiceNotFound If unable to find HTML builder
     * @throws DefinitionException
     * @throws MapperException
     */
    public function getClasses(Term $term)
    {
        $now           = Alaress_Date_Format::now();
        $isTermCurrent = $term->contains($now);
        $hideTimetable = $this->getUser()->getHideTimetable();
        $hideClasses   = $isTermCurrent && $hideTimetable;

        // Are we not showing classes ?
        if ($hideClasses) {
            $gridData = [
                'term'       => $term,
                'classList'  => $this->folderMapper->createCollection(),
                'classTypes' => [],
                'courses'    => new Collection(),
            ];
        } else {
            $classList = new ClassList(
                $this->getUser(),
                $this->folderMapper,
                $this->timetableColourMapper,
                $this->courseSpecificationMapper,
                $this->courseImplementationMapper
            );
            $gridData  = $classList->getClassList($term, $now);
        }


        $gridData = array_merge($gridData, [
            'now'           => $now,
            'terms'         => $this->termMapper->findAll()->sortBy('start', 'desc'),
            'hideTimetable' => $hideTimetable,
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/classes.twig', $gridData)->setTab('classes'));
    }

    /* Getters/Setters
     */
}
