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
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\StudentReportCard\Conversation;
use Schoolbox\Error;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Filter;
use Schoolbox\Filter\Page as FilterPage;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Learning\Assessment\DueWork;

class Controller extends Base
{

    /**
     * @var Term\Mapper
     */
    private $termMapper;

    /**
     * @var GradeGroup\Model
     */
    private $defaultGradeGroup;

    /**
     * @var Folder\Mapper
     */
    private $folderMapper;

    /**
     * @var Error\Mapper
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

    /**
     * @var Access
     */
    private $access;

    /** @var FilterSelection\Mapper */
    private $selectionMapper;


    /** @var AssessmentFilters\IdFinder */
    private $assessmentIdFinder;

    /**
     * @var AssessmentFilters\FactoryFactory
     */
    private $filterFactoryFactory;


    /**
     * @param Error\Mapper                     $errorMapper
     * @param AssessmentMapperProxy            $assessmentMapperProxy
     * @param Folder\Mapper                    $folderMapper
     * @param Term\Mapper                      $termMapper
     * @param GradeGroup\Model                 $defaultGradeGroup
     * @param RubricMapper                     $rubricMapper
     * @param FilterSelection\Mapper           $filterSelectionMapper
     * @param Access                           $access
     * @param AssessmentFilters\IdFinder       $assessmentIdFinder
     * @param AssessmentFilters\FactoryFactory $filterFactoryFactory
     */
    public function __construct(
        Error\Mapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        Folder\Mapper $folderMapper,
        Term\Mapper $termMapper,
        GradeGroup\Model $defaultGradeGroup,
        RubricMapper $rubricMapper,
        FilterSelection\Mapper $filterSelectionMapper,
        Access $access,
        AssessmentFilters\IdFinder $assessmentIdFinder,
        AssessmentFilters\FactoryFactory $filterFactoryFactory
    )
    {
        $this->errorMapper           = $errorMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->termMapper            = $termMapper;
        $this->folderMapper          = $folderMapper;
        $this->defaultGradeGroup     = $defaultGradeGroup;
        $this->rubricMapper          = $rubricMapper;
        $this->access                = $access;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->selectionMapper       = $filterSelectionMapper;
        $this->assessmentIdFinder    = $assessmentIdFinder;
    }


    /**
     * Redirect user to their own grades page for the current term
     */
    public function getGradesForCurrentUser()
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningGrades.getGrades', [
                    'userId'   => $this->getUser()->getId(),
                    'termCode' => $this->getTermMapper()->findForToday()->getCode()
                ]
            )
        );
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
    public function getGrades(User $viewee, Term\Model $term)
    {
        // Add folders with response or feedback
        $folders = $this->findFoldersWithStudentInteractions($viewee, $term);

        // Load Teachers
        foreach($folders as $folder) {
            $folder->loadTeachers();
        }

        // Get all assessments for this user in the specified term, grouped by folder
        $assessmentsPerFolder = $this->getAssessmentMapperProxy()
                                     ->findAllByStudent($viewee, $term)
                                     ->groupByCallback(function (IAssessable $assessment) {
                                         return $assessment->getBaseContext()->getFolder()->getId();
                                     });

        $showOverallGrade = $this->getConfigLoader()
                                 ->get('show_overall_grade');

        /** @var GenericCollection|Markbook[] Indexed by folder.id */
        $markbooks = new GenericCollection(Markbook::class);
        // Generate markbooks for these assessments
        foreach($assessmentsPerFolder as $folderId => $assessments) {
            $assessment = $assessments->first();
            if($assessment instanceof IAssessable &&
                ($showOverallGrade || $this->access->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder()))
            ) {
                $markbooks->setOffset($folderId, new Markbook($assessments, $this->defaultGradeGroup, $this->getUser()));
            }
        }


        $this->getResponse()->content->set($this->buildHtmlOutput('@learningGrades/summary.twig', [
            'student'          => $viewee,
            'term'             => $term,
            'terms'            => $this->termMapper->findAll()->sortBy('start', 'desc'),
            'folders'          => $folders,
            'markbooks'        => $markbooks,
            'showOverallGrade' => $showOverallGrade,
            'mailHandle'       => $this->getSession() ->get('mailhandle'),
        ])->setTab('grade'));
    }


    /**
     * Generate the page with filter panel where we will insert a filtered grades report
     *
     * Also generates the Teacher/Comment section for all student-involved folders
     *
     * @param User         $viewee
     * @param Term\Model   $term
     * @param Folder\Model $subject
     * @throws RouteNotFound If unable to generate the route to fetch the filtered report
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException If a specified filter definition class cannot be found
     * @throws \DomainException If the filter FactoryFactory has not defined its filterable entity
     * @throws ServiceNotFound If unable to fetch the HTML output renderer from the DI
     * @throws DefinitionException If specified mapper join is not defined
     * @throws Filter\BrokenFilterException If a injected filter is unable to be processed
     * @throws \Schoolbox\Mapper\Exception If a database column alias is incorrect
     */
    public function getGradesForSubject(User $viewee, Term\Model $term, Folder\Model $subject)
    {
        $page        = FilterPage\Model::PAGE_GRADES;
        $route       = $this->getRouter()->generate('learningGrades.postReport', [
            'userId'    => $viewee->getId(),
            'termCode'  => $term->getCode(),
            'subjectId' => $subject->getId(),
        ]);
        $request     = $this->getRequest()->query->getArrayCopy();
        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, $request);

        /* Build an markbook of assessments that:
         * * Belong to this folder
         * * Are due in the specified term
         * * Are not Assessment Specs
         * * Are not before their publish date (ie are not KEY_UNPUBLISHED)
         */
        $filters       = [
            AssessmentFilters\Folder\StudentInteractions\Definition::class => $subject->getId(),
            AssessmentFilters\Term\Due\Definition::class                   => $term->getId(),
            AssessmentFilters\NullableContext\UnitSpec\Definition::class   => [],
            AssessmentFilters\TemporalState\Definition::class              => [
                AssessmentFilters\TemporalState\Definition::KEY_UPCOMING,
                AssessmentFilters\TemporalState\Definition::KEY_OPEN,
                AssessmentFilters\TemporalState\Definition::KEY_OVERDUE,
                AssessmentFilters\TemporalState\Definition::KEY_CLOSED,
            ]
        ];
        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters);
        $assessments   = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $assessments   = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, $viewee);
        $markbook      = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());

        // Load conversation history (this also implicitly loads folder's teachers)
        $conversation = new Conversation($this->getConfigLoader(), $viewee, $subject);

        // Show the Current Grade Average for instructors and for students if the config var is on
        $isInstructor       = $this->access->isInstructor($this->getUser(), $subject);
        $canSeeGradeAverage = $isInstructor || $this->getConfigLoader()->get('show_overall_grade');

        // Get folders for drop-down.
        $folders = $this->findFoldersWithStudentInteractions($viewee, $term);

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learningGrades/gradesOuter.twig', [
                'student'            => $viewee,
                'filterPanel'        => $filterPanel,
                'term'               => $term,
                'terms'              => $this->termMapper->findAll()->sortBy('start', 'desc'),
                'subject'            => $subject,
                'folders'            => $folders,
                'canSeeGradeAverage' => $canSeeGradeAverage,
                'markbook'           => $markbook,
                'conversation'       => $conversation,
            ])->setTab('grade')->setTitle("Grades for {$subject->getTitle()}")
        );
    }


    /**
     * @param User         $viewee
     * @param Term\Model   $term
     * @param Folder\Model $subject
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws Filter\BrokenFilterException If a filter is unable to be processed
     * @throws ServiceNotFound If unable to locate HTML web Output
     * @throws DefinitionException If specified mapper join is not defined
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @todo convert to a GET route
     */
    public function postReport(User $viewee, Term\Model $term, Folder\Model $subject)
    {
        $request         = $this->request->post['filter_selection'] ?: [];
        $page            = FilterPage\Model::PAGE_GRADES;

        $codec           = $this->filterFactoryFactory->buildCodec();
        $userSelections  = $codec->convertRequestToSelections($this->getUser(), $page, $request);

        // TODO don't save selections for non user-configurable page filters
        $this->selectionMapper->updateSelections($this->getUser(), $page, $userSelections);

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $page);

        /* Merge filters to ensure that assessments:
         * * Belong to this folder
         * * Are due in the specified term
         * * Are not Assessment Specs
         * * Are not before their publish date (ie are not KEY_UNPUBLISHED)
         */
        $filters = array_merge($filters, [
            AssessmentFilters\Folder\StudentInteractions\Definition::class => $subject->getId(),
            AssessmentFilters\Term\Due\Definition::class                   => $term->getId(),
            AssessmentFilters\NullableContext\UnitSpec\Definition::class   => [],
            AssessmentFilters\TemporalState\Definition::class              => [
                AssessmentFilters\TemporalState\Definition::KEY_UPCOMING,
                AssessmentFilters\TemporalState\Definition::KEY_OPEN,
                AssessmentFilters\TemporalState\Definition::KEY_OVERDUE,
                AssessmentFilters\TemporalState\Definition::KEY_CLOSED,
            ]
        ]);

        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters);

        // Fetch assessments & participants
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $assessments = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments);

        // Show the graph for:
        // * instructors (always)
        // * students (if the config var is on, and their personal grade average setting is on)
        // We no longer show the grade graph at all if there's no comparative grades.
        $canSeeGraph = $this->access->isInstructor($this->getUser(), $subject)
            || ($viewee->getDisplayGradeGraph() && $this->getConfigLoader()
                                                        ->get('display_grade_graph'));

        // Only show the "send to showcase" trophy button to teachers and students, not parents
        $canSeeShowcaseButton = $this->getUser()->getRole()->isStaff() || $this->getUser()->getRole()->isStudent();

        // Let markbook calculate the statistics
        $filteredMarkbook = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());

        $graph = $this->buildGraph($canSeeGraph, $filteredMarkbook, $viewee);

        $longitudinalRubric = $this->getLongitudinalRubricData($assessments, $viewee);

        $this->getResponse()->content->set($this->buildJsonOutput([
            'status'  => 200,
            'content' => $this->buildUnthemedHtmlOutput('@learningGrades/gradesInner.twig', [
                'student'            => $viewee,
                'markbook'           => $filteredMarkbook,
                'canSeeGraph'        => $canSeeGraph,
                'canSeeShowcaseButton' => $canSeeShowcaseButton,
                'graph'              => $graph,
                'longitudinalRubric' => $longitudinalRubric,
                'returnUrlSuffix'    => '?' . Redirect::RETURN_URL . '=' . Redirect::encode($_SERVER['REQUEST_URI']),
            ])->getAsString(),
        ]));

    }


    /**
     * Fetch the set of folders where the specified user has submitted responses or received feedback.
     *
     * @param User       $student
     * @param Term\Model $term
     * @return GenericCollection|Folder\Model[] Sorted by name, indexed by ID
     * @throws DefinitionException If specified mapper join is not defined
     */
    private function findFoldersWithStudentInteractions(User $student, Term\Model $term)
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
     * @var string     $graph ['info']         json encoded    // TODO Replace w/ object
     * @var string     $graph ['assessments']  json encoded
     * @var string     $graph ['data']         json encoded
     * @var string     $graph ['scatter']      json encoded
     */
    private function buildGraph($canSeeGraph, Markbook $markbook, User $viewee)
    {
        if(!$canSeeGraph) {
            return [];
        }

        // Get the data for the graph
        $graphInfo        = [];
        $graphAssessments = [];
        $graphData        = [];
        $graphScatter     = [];


        $statistics = $markbook->getAssessmentStatistics();

        foreach($markbook->getAssessments() as $assessment) {

            $graphAssessments[] = $assessment->getTitle();

            $participant = $assessment->getParticipantIfExtant($viewee);
            $feedback    = $participant ? $participant->findAuthoritativeVisibleFeedback($this->getUser()) : null;

            if($feedback && !is_null($feedback->getNormalisedMark())) {
                $graphScatter[] = (float)$feedback->getNormalisedMark();

                if(!empty($feedback->getHumanReadableMark()) || !empty($feedback->getComment())) {
                    $graphInfo[] = [
                        'displayMark'  => $feedback->getHumanReadableMark(),
                        'gradientMark' => (int)($feedback->getNormalisedMark() / 10),
                        'info'         => null === $feedback->getComment() ? '' : $feedback->getComment(),
                    ];
                }
                else {
                    $graphInfo[] = [];
                }
            }
            else {
                $graphScatter[] = '';
                $graphInfo[]    = [];
            }

            $hasStats = $statistics[$assessment->getId()]['count'] > 0 && $assessment->getMarkScheme()
                                                                                     ->isQuantitative();

            if($hasStats) {
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
            }
            else {
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
     * @param GenericCollection|IAssessable[] $assessments
     * @param User                            $student
     * @return array
     */
    protected function getLongitudinalRubricData(GenericCollection $assessments, User $student)
    {
        if(!$this->getConfigLoader()->get('enable_grades_criteria')) {
            // only populate this if we're actually allowed to see it
            return [];
        }

        // Filter to dueWork with marks visible to current user
        $user = $this->getUser();
        $dueWorksWithVisibleMarks = $assessments->filter(function (IAssessable $assessment) use ($user) {
            return $assessment instanceof DueWork\Model && $assessment->isFeedbackVisibleToUser($user);
        });

        return $this->rubricMapper->getLongitudinal($dueWorksWithVisibleMarks, $student);
    }


    /* Getters/Setters
     */

    /**
     * @return Error\Mapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }


    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }


    /**
     * @return Term\Mapper
     */
    protected function getTermMapper()
    {
        return $this->termMapper;
    }


    /**
     * @return Folder\Mapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }


    /**
     * @return GradeGroup\Model
     */
    protected function getDefaultGradeGroup()
    {
        return $this->defaultGradeGroup;
    }
}
