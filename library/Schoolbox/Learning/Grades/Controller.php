<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Grades;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\StudentReportCard\Conversation;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Statistics;
use Schoolbox\Learning\Classes\ClassList;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\Assessment\Term;
use Schoolbox\UserMapper;
use Timetable_ColourMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Assessment\Quiz\Model as AssessmentQuiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Permission\Access\Access;
use \Alaress_Date_Format;
use Schoolbox\Filter;
use Schoolbox\Filter\Option as FilterOption;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\Mapper;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;

class Controller extends Base
{

    /**
     * @var QuizMapper
     */
    private $quizMapper;

    /**
     * @var QuizAttemptMapper
     */
    private $quizAttemptMapper;

    /**
     * @var ShowcaseMapper
     */
    private $showcaseMapper;

    /**
     * @var Term\Mapper
     */
    private $termMapper;

    /**
     * @var GradeGroup
     */
    private $defaultGradeGroup;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var ErrorMapper
     */
    private $errorMapper;

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;
    /**
     * @var RubricMapper
     */
    private $rubricMapper;

    /** @var Timetable_ColourMapper */
    private $timetableColourMapper;

    /** @var CourseImplementationMapper */
    private $courseImplementationMapper;

    /** @var CourseSpecificationMapper */
    private $courseSpecificationMapper;

    /**
     * @var Access
     */
    private $access;

    /** @var Filter\Mapper */
    private $filterMapper;


    /** @var FilterSelection\Mapper */
    private $selectionMapper;


    /** @var AssessmentFilters\IdFinder */
    private $assessmentIdFinder;

    /**
     * @var AssessmentFilters\FactoryFactory
     */
    private $filterFactoryFactory;


    /**
     * @param ErrorMapper                $errorMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param Folder\Mapper               $folderMapper
     * @param Term\Mapper                 $termMapper
     * @param GradeGroup                 $defaultGradeGroup
     * @param ShowcaseMapper             $showcaseMapper
     * @param RubricMapper               $rubricMapper
     * @param QuizMapper                 $quizMapper
     * @param QuizAttemptMapper          $quizAttemptMapper
     * @param Timetable_ColourMapper     $timetableColourMapper
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param CourseSpecificationMapper  $courseSpecificationMapper
     * @param FilterSelection\Mapper     $filterSelectionMapper
     * @param Access                     $access
     * @param Filter\Mapper              $filterMapper
     * @param AssessmentFilters\IdFinder $assessmentIdFinder
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     */
    public function __construct(
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        Folder\Mapper $folderMapper,
        Term\Mapper $termMapper,
        GradeGroup $defaultGradeGroup,
        ShowcaseMapper $showcaseMapper,
        RubricMapper $rubricMapper,
        QuizMapper $quizMapper,
        QuizAttemptMapper $quizAttemptMapper,
        Timetable_ColourMapper $timetableColourMapper,
        CourseImplementationMapper $courseImplementationMapper,
        CourseSpecificationMapper $courseSpecificationMapper,
        FilterSelection\Mapper $filterSelectionMapper,
        Access $access,
        Filter\Mapper $filterMapper,
        AssessmentFilters\IdFinder $assessmentIdFinder,
        AssessmentFilters\FactoryFactory $filterFactoryFactory
    ) {
        $this->errorMapper                = $errorMapper;
        $this->assessmentMapperProxy      = $assessmentMapperProxy;
        $this->termMapper                 = $termMapper;
        $this->folderMapper               = $folderMapper;
        $this->defaultGradeGroup          = $defaultGradeGroup;
        $this->showcaseMapper             = $showcaseMapper;
        $this->rubricMapper               = $rubricMapper;
        $this->quizMapper                 = $quizMapper;
        $this->quizAttemptMapper          = $quizAttemptMapper;
        $this->timetableColourMapper      = $timetableColourMapper;
        $this->access                     = $access;
        $this->filterFactoryFactory       = $filterFactoryFactory;
        $this->filterMapper = $filterMapper;
        $this->selectionMapper = $filterSelectionMapper;
        $this->assessmentIdFinder = $assessmentIdFinder;
    }


    /**
     * Display the grade summary page. With a list of classes/groups that have student interactions
     * for the specified Viewee and Term
     *
     * @param User       $viewee
     * @param Term\Model $term
     * @throws DefinitionException If a required mapper join is not defined
     * @throws ServiceNotFound If unable to locate HTML web Output
     */
    public function getSummaryGrades(User $viewee, Term\Model $term)
    {
        // Regardless of whether the term is current, only show folders where the student
        // has responses or feedback

        // Add folders with response or feedback
        $folders = $this->findFoldersWithStudentInteractions($viewee, $term);

        // Load Teachers
        foreach ($folders as $folder) {
            $folder->loadTeachers();
        }

        // Get all assessments for this user in the specified term, grouped by folder
        $assessmentsPerFolder = $this->getAssessmentMapperProxy()
                                     ->findAllByStudent($viewee, $term)
                                     ->groupByCallback(function (IAssessable $assessment) {
                                         return $assessment->getBaseContext()->getFolder()->getId();
                                     });

        $showOverallGrade = $this->getConfigLoader()->get('show_overall_grade');


        /** @var GenericCollection|Markbook[] Indexed by folder.id */
        $markbooks = new GenericCollection(Markbook::class);
        // Generate markbooks for these assessments
        foreach ($assessmentsPerFolder as $folderId => $assessments) {
            $assessment = $assessments->first();
            if ($assessment instanceof IAssessable
                && ($showOverallGrade
                    || $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder())
                )
            ) {
                $markbooks->setOffset($folderId, new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser()));
            }
        }

        // Squish this mess out

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningGrades/summary.twig', [
            'student'    => $viewee,
            'term'       => $term,
            'terms'      => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'    => $folders,
            'markbooks'  => $markbooks,
            'showOverallGrade' => $showOverallGrade,
            'mailHandle' => $this->getSession()->get('mailhandle'),
        ])->setTab('grade'));
    }


    /**
     * Convenience route to redirect to equivalent filtered report
     *
     * @param int $userId
     * @param int $subjectId
     * @param Term\Model $term
     * @throws RouteNotFound If unable to generate the route for the filtered grades page
     */
    public function getGradesForSubject($userId, $subjectId, Term\Model $term)
    {
        $route       = $this->getRouter()->generate('learningGrades.getGrades', ['userId' => $userId]);

        $selections = new GenericCollection(GenericCollection::class);
        $filters    = $this->filterMapper->findForEntity(\Schoolbox\Learning\Assessment\DataModel\Mapper::class);
        foreach($filters as $filter){
            $selections[$filter->getId()] = $this->selectionMapper->createCollection();

            if(Filter\Filters\Assessment\StudentFolder\Definition::class === $filter->getClass()){
                $selections[$filter->getId()][$subjectId] = $this->createSelectionModel($this->selectionMapper, $filter, (string)$subjectId);
            } elseif(Filter\Filters\Assessment\Term\Due\Definition::class === $filter->getClass()){
                $selections[$filter->getId()][$term->getId()] = $this->createSelectionModel($this->selectionMapper, $filter, (string)$term->getId());
            }
        }

        $codec = new Filter\Codec();
        $getArgString = $codec->convertSelectionsToQueryString($selections);

        $this->getResponse()->redirect->to("{$route}?{$getArgString}");
    }


    /**
     * Helper method to create a Filter Selection model.
     *
     * Useful for redirect routes to convert URL fragments into filter query args
     *
     * @param Filter\Selection\Mapper $selectionMapper
     * @param Filter\Model            $filter
     * @param string                  $key
     * @return Filter\Selection\Model
     */
    private function createSelectionModel(Filter\Selection\Mapper $selectionMapper, Filter\Model $filter, $key)
    {
        $selection = $selectionMapper->createModel();
        $selection
            ->setFilter($filter)
            ->setUser($this->getUser())
            ->setPage(FilterOption\Model::PAGE_MARK_BOOK)
            ->setKey($key)
            ->setSelected(true);
        return $selection;
    }


    /**
     * Generate the page with filter panel where we will insert a filtered grades report
     *
     * Also generates the Teacher/Comment section for all student-involved folders
     *
     * @param User $viewee
     * @throws RouteNotFound If unable to generate the route to fetch the filtered report
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException If a specified filter definition class cannot be found
     * @throws \DomainException If the filter FactoryFactory has not defined its filterable entity
     * @throws ServiceNotFound If unable to fetch the HTML output renderer from the DI
     * @throws DefinitionException If specified mapper join is not defined
     */
    public function getGrades(User $viewee)
    {
        $page      = FilterOption\Model::PAGE_GRADES;
        $route     = $this->getRouter()->generate('learningGrades.postGrades', ['userId' => $viewee->getId()]);
        $queryArgs = $this->getRequest()->query->getArrayCopy();

        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, $queryArgs);

        // Is a user looking at their own markbook, teacher/parent looking at student?
        $pageTitle = 'Grades';
        if ($viewee->getId() !== $this->getUser()->getId()) {
            $pageTitle .= " for {$viewee->getFullName()}";
        }

        $involvedFolders = $this->findFoldersWithStudentInteractions($viewee);

        // Grab the students conversation history (this also implicitly loads teachers)
        $conversationsPerFolder = new GenericCollection(Conversation::class);
        foreach($involvedFolders as $soleFolder){
            $conversationsPerFolder[$soleFolder->getId()] = new Conversation($this->getConfigLoader(), $viewee, $soleFolder);
        }

        // Is a single folder being filtered to

        /** @var AssessmentFilters\StudentFolder\Component $folderComponent */
        $folderComponent = $filterPanel->getComponents()[AssessmentFilters\StudentFolder\Definition::class];

        /** @var GenericCollection|FilterSelection\Model[] $selections */
        $selections = $folderComponent->toContext()['userSelections'];
        $soleSelectedFolderId = null;
        foreach($selections as $selection){
            // Is this folder selected?
            if($selection->getSelected()){
                // But we already encountered a selected folder?
                if(null !== $soleSelectedFolderId){
                    // Set it to null and conclude we do not have a sole-selected folder
                    $soleSelectedFolderId = null;
                    break;
                }
                // This may be the sole-selected folder, but we need to examine the rest to be sure.
                $soleSelectedFolderId = $selection->getKey();
            }
        }
        $soleFolder = $involvedFolders[$soleSelectedFolderId];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learningGrades/gradesOuter.twig', [
                'viewee'                 => $viewee,
                'filterPanel'            => $filterPanel,
                'soleFolder'             => $soleFolder,
                'folders'                => $involvedFolders,
                'conversationsPerFolder' => $conversationsPerFolder,
            ])->setTab('grade')->setTitle($pageTitle)
        );
    }


    /**
     * @param User $viewee
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws Filter\BrokenFilterException If a filter is unable to be processed
     * @throws ServiceNotFound If unable to locate HTML web Output
     * @todo merge the with getGrades() and distinguish via synchronous vs AJAX
     */
    public function postGrades(User $viewee)
    {
        $postFilterSelections = $this->request->post['filter_selection'] ?: [];

        $page          = FilterOption\Model::PAGE_GRADES;
        $keyConverters = $this->filterFactoryFactory->buildKeyConverters($page);
        $codec         = new Filter\Codec();

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelectionModels($this->selectionMapper, $keyConverters, $this->getUser(), $page, $postFilterSelections);

        // Don't save selections if viewing someone else's grades
        // TODO Add ability to save per viewee. SA-3444
        if($viewee->getId() === $this->getUser()->getId()){
            $this->selectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
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


        // Fetch all folders
        $folders = $this->folderMapper->createCollection();
        foreach($assessments as $assessment){
            $folder = $assessment->getBaseContext()->getFolder();
            $folders[$folder->getId()] = $folder;
        }

        // Instructors can see the graph regardless of the config settings. (they must have WRITE+ perm to all folders)
        $isInstructor = true;
        foreach($folders as $folder){
            if(!$this->getAccess()->isInstructor($this->getUser(), $folder)){
                $isInstructor = false;
                break;
            }
        }

        // Show the graph for:
        // * instructors (always)
        // * students (if the config var is on, and their personal grade average setting is on)
        // We no longer show the grade graph at all if there's no comparative grades.
        $canSeeGraph = $isInstructor || ($viewee->getDisplayGradeGraph() && $this->getConfigLoader()->get('display_grade_graph'));

        // Show the Current Grade Average for instructors and for students if the config var is on
        $canSeeGradeAverage = $isInstructor || $this->getConfigLoader()->get('show_overall_grade');

        // Let markbook calculate the statistics
        $aggregateMarkbook   = new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser());

        $graph = $this->buildGraph($canSeeGraph, $aggregateMarkbook, $viewee);




        // One rubric graph for all filtered assessments TODO
//        $longitudinalRubrics = new Collection();
//        foreach($folders as $folder){
//            $longitudinalRubrics[$folder->getId()] = $this->getLongitudinalRubricData($folder, $viewee, $term);
//        }


        $this->getResponse()->content->set($this->buildJsonOutput([
            'status'  => 200,
            'content' => $this->buildUnthemedHtmlOutput('@learningGrades/gradesInner.twig', [
                'student'            => $viewee,
                'canSeeGradeAverage' => $canSeeGradeAverage,
                'folders'            => $folders,
                'aggregateMarkbook'  => $aggregateMarkbook,
                'canSeeGraph'        => $canSeeGraph,
                'graph'              => $graph,
                'returnUrlSuffix'    => '?' . Redirect::RETURN_URL . '=' . Redirect::encode($_SERVER['REQUEST_URI']),
//            'longitudinalRubric' => $this->getLongitudinalRubricData($subject, $viewee, $term),

            ])->getAsString(),
        ]));

    }


    /**
     * Fetch the set of folders where the specified user has submitted responses or received feedback.
     *
     * @param User $student
     * @param null|Term\Model $term
     * @return GenericCollection|Folder\Model[] Sorted by name, indexed by ID
     * @throws DefinitionException If specified mapper join is not defined
     */
    private function findFoldersWithStudentInteractions(User $student, Term\Model $term = null)
    {
        /** @var GenericCollection|Folder\Model[] $involvedFolders */
        $involvedFolders = $this->folderMapper->createCollection()
                                              ->mergeUnique($this->folderMapper->findFoldersWithFeedbackToStudent($student, $term), 'id')
                                              ->mergeUnique($this->folderMapper->findFoldersWithResponses($student, $term), 'id')
                                              ->indexBy('id')
                                              ->sortBy('name', 'ASC', null, true);
        return $involvedFolders;
    }


    /**
     * Builds an array of data used by twig to render the assessment box-and-whiskers graph
     *
     * @param bool     $canSeeGraph
     * @param Markbook $markbook
     * @param User     $viewee
     * @return mixed[][] $graph
     * @var string $graph['info']         json encoded    // TODO Replace w/ object
     * @var string $graph['assessments']  json encoded
     * @var string $graph['data']         json encoded
     * @var string $graph['scatter']      json encoded
     */
    private function buildGraph($canSeeGraph, Markbook $markbook, User $viewee)
    {
        if(!$canSeeGraph){
            return [];
        }

        // Get the data for the graph
        $graphInfo        = [];
        $graphAssessments = [];
        $graphData        = [];
        $graphScatter     = [];


        $statistics = $markbook->getAssessmentStatistics();

        foreach ($markbook->getAssessments() as $assessment) {

            $graphAssessments[] = $assessment->getTitle();

            $participant = $assessment->getParticipantIfExtant($viewee);
            $feedback    = $participant ? $participant->findMostRecentVisibleFeedback($this->getUser()) : null;

            if ($feedback) {
                $graphScatter[] = (float)$feedback->getNormalisedMark();

                if (!empty($feedback->getHumanReadableMark()) || !empty($feedback->getComment())) {
                    $graphInfo[] = [
                        'displayMark'  => $feedback->getHumanReadableMark(),
                        'gradientMark' => (int)($feedback->getNormalisedMark() / 10),
                        'info'         => null === $feedback->getComment() ? '' : $feedback->getComment(),
                    ];
                } else {
                    $graphInfo[] = [];
                }
            } else {
                $graphScatter[] = '';
                $graphInfo[]    = [];
            }

            $hasStats = $statistics[$assessment->getId()]['count'] > 0 && $assessment->getMarkScheme()->isQuantitative();

            if ($hasStats) {
                $graphData[] = [
                    // Minimum
                    [
                        'info'  => null,
                        'uid'   => null,
                        'value' => $statistics[$assessment->getId()]['minNormalized'],
                    ],
                    // 1st Quartile
                    [
                        'info'  => null,
                        'uid'   => null,
                        // TODO: Find the real way to calculate the 1st/3rd quartiles
                        'value' => $statistics[$assessment->getId()]['meanNormalized'] - $statistics[$assessment->getId()]['sdNormalized'],
                    ],
                    // Median
                    [
                        'info'  => null,
                        'uid'   => null,
                        'value' => $statistics[$assessment->getId()]['meanNormalized'],
                    ],
                    // 3rd Quartile
                    [
                        'info'  => null,
                        'uid'   => null,
                        // TODO: Find the real way to calculate the 1st/3rd quartiles
                        'value' => $statistics[$assessment->getId()]['meanNormalized'] + $statistics[$assessment->getId()]['sdNormalized'],
                    ],
                    // Maximum
                    [
                        'info'  => null,
                        'uid'   => null,
                        'value' => $statistics[$assessment->getId()]['maxNormalized'],
                    ],
                ];
            } else {
                $graphData[] = [];
            }
        }
        return [
            'info'        => json_encode($graphInfo),
            'assessments' => json_encode($graphAssessments),
            'data'        => json_encode($graphData),
            'scatter'     => json_encode($graphScatter),
        ];
    }



    /**
     * @param Folder\Model     $subject
     * @param User       $student
     * @param null|Alaress_Date_Format $start
     * @param null|Alaress_Date_Format $end
     * @return array
     * @todo rewrite this to take a set of terms to make it more efficient
     */
    protected function getLongitudinalRubricData(Folder\Model $subject, User $student, Alaress_Date_Format $start = null, Alaress_Date_Format $end = null)
    {
        if (!$this->getConfigLoader()->get('enable_grades_criteria')) {
            // only populate this if we're actually allowed to see it
            return [];
        }

        return $this->getRubricMapper()->getLongitudinal($subject, $student, $start, $end);
    }


    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }


    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }


    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }


    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @return static
     */
    protected function setAssessmentMapperProxy(AssessmentMapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }


    /**
     * @return Term\Mapper
     */
    protected function getTermMapper()
    {
        return $this->termMapper;
    }


    /**
     * @param Term\Mapper $termMapper
     * @return static
     */
    protected function setTermMapper(Term\Mapper $termMapper)
    {
        $this->termMapper = $termMapper;
        return $this;
    }


    /**
     * @return Folder\Mapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }


    /**
     * @param Folder\Mapper $folderMapper
     * @return static
     */
    protected function setFolderMapper(Folder\Mapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }


    /**
     * @return GradeGroup
     */
    protected function getDefaultGradeGroup()
    {
        return $this->defaultGradeGroup;
    }


    /**
     * @param GradeGroup $defaultGradeGroup
     * @return static
     */
    protected function setDefaultGradeGroup(GradeGroup $defaultGradeGroup)
    {
        $this->defaultGradeGroup = $defaultGradeGroup;
        return $this;
    }


    /**
     * @return ShowcaseMapper
     */
    protected function getShowcaseMapper()
    {
        return $this->showcaseMapper;
    }


    /**
     * @return RubricMapper
     */
    public function getRubricMapper()
    {
        return $this->rubricMapper;
    }


    /**
     * @param RubricMapper $rubricMapper
     * @return static
     */
    public function setRubricMapper($rubricMapper)
    {
        $this->rubricMapper = $rubricMapper;
        return $this;
    }


    /**
     * @param ShowcaseMapper $showcaseMapper
     * @return static
     */
    protected function setShowcaseMapper(ShowcaseMapper $showcaseMapper)
    {
        $this->showcaseMapper = $showcaseMapper;
        return $this;
    }


    /**
     * @return Timetable_ColourMapper
     */
    public function getTimetableColourMapper()
    {
        return $this->timetableColourMapper;
    }


    /**
     * @param Timetable_ColourMapper $timetableColourMapper
     * @return static
     */
    public function setTimetableColourMapper(Timetable_ColourMapper $timetableColourMapper)
    {
        $this->timetableColourMapper = $timetableColourMapper;
        return $this;
    }


    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
    }


    /**
     * @param Access $access
     * @return static
     */
    protected function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }
}
