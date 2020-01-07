<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Grades;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use InvalidArgumentException;
use LogicException;
use ReflectionException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\StudentReportCard\Conversation;
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Filter\Page as FilterPage;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Quiz;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use TypeError;
use UnexpectedValueException;
use function func_get_args;

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
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var RubricDataSource
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
     * @var Filter\IFactoryFactory
     */
    private $filterFactoryFactory;


    /**
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param Folder\Mapper              $folderMapper
     * @param Term\Mapper                $termMapper
     * @param GradeGroup\Model           $defaultGradeGroup
     * @param RubricDataSource           $rubricMapper
     * @param FilterSelection\Mapper     $filterSelectionMapper
     * @param Access                     $access
     * @param AssessmentFilters\IdFinder $assessmentIdFinder
     * @param Filter\IFactoryFactory     $filterFactoryFactory
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        Folder\Mapper $folderMapper,
        Term\Mapper $termMapper,
        GradeGroup\Model $defaultGradeGroup,
        RubricDataSource $rubricMapper,
        FilterSelection\Mapper $filterSelectionMapper,
        Access $access,
        AssessmentFilters\IdFinder $assessmentIdFinder,
        Filter\IFactoryFactory $filterFactoryFactory
    )
    {
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
    public function getGradesForCurrentUser(): void
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
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws LogicException If cyclic graph detected during translation
     * @throws UnexpectedValueException If unable to merge project participant collections
     */
    public function getGrades(User $viewee, Term\Model $term): void
    {
        $filteredWorkTypes = WorkType\Mapper::getInstance()->findByColumn('show_on_grades_page', 1);

        if (\count($filteredWorkTypes)) {
            $assessments = $this->getAssessmentMapperProxy()->findAllByStudentBetweenDates($viewee, false, $term->getStart(), $term->getActualFinish());

            $this->getCustomGrades($viewee, $assessments, null, $term, $filteredWorkTypes);
            return;
        }

        // Add folders with response or feedback
        $folders = $this->findFoldersWithStudentInteractions($viewee, $term);

        // Get all assessments for this user in the specified term, grouped by folder
        $assessmentsPerFolder = $this->assessmentMapperProxy
            ->findAllByStudent($viewee, false, $term)
            ->filter(function (IAssessable $a) use ($viewee) {
                return $a->isPublished($viewee) && $a->isParticipant($viewee);
            })
            ->groupByCallback(function (IAssessable $assessment) {
                return $assessment->getBaseContext()->getFolder()->getId();
            });

        /** @var GenericCollection|Folder\Model[] $folders */
        $folders = $folders->filter(function (Folder\Model $folder) use ($assessmentsPerFolder) {
            return $assessmentsPerFolder->keyExists($folder->getId());
        });

        // Load Teachers
        foreach($folders as $folder) {
            $folder->loadTeachers();
        }

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

        $yearUrl = $this->getYearUrl($viewee->getId());
        $this->getResponse()->content->set($this->buildHtmlOutput('@learningGrades/summary.twig', [
            'student'          => $viewee,
            'term'             => $term,
            'terms'            => $this->termMapper->findAll()->sortBy('start', 'desc'),
            'folders'          => $folders,
            'markbooks'        => $markbooks,
            'showOverallGrade' => $showOverallGrade,
            'mailHandle'       => $this->getSession()->get('mailhandle'),
            'yearUrl'          => $yearUrl,
        ])->setTab('grade'));
    }


    /**
     *
     * @param User                     $viewee
     * @param Collection|IAssessable[] $assessments
     * @param null|int                 $year Null if looking at a term, instead of a full-year
     * @param Term\Model               $term
     * @param Collection               $workTypes
     * @throws ServiceNotFound If unable to find mail handler service
     * @throws UnexpectedValueException
     * @throws InsufficientHydrationException
     */
    protected function getCustomGrades(User $viewee, Collection $assessments, $year, Term\Model $term, Collection $workTypes): void
    {
        $workTypeNames = $workTypes->getPropertyValues('name');

        // Storage for each folders markbook
        $markbooks = new GenericCollection(Markbook::class);
        $markbooksFolders = [];

        // Keep a collection of all folders student has an assessment in for use in the dropdown
        /** @var IAssessable[][]|GenericCollection[] $assessmentTasks */
        $assessmentTasks = [];

        $showOverallGrade = $this->getConfigLoader()->get('show_overall_grade');

        $folders = $this->folderMapper->createCollection();

        $visibility = new Visibility($this->access);

        foreach ($assessments as $assessment) {
            $folder = $assessment->getContext()->getFolder();

            if ($folder instanceof Folder\Model && !$folder->isDeleted()) {
                if (!isset($markbooksFolders[$folder->getId()])) {
                    $markbooksFolders[$folder->getId()] = $this->getAssessmentMapperProxy()->createCollection();
                    $folder->loadTeachers();
                    $folders->add($folder);
                }

                if (
                    ($assessment instanceof DueWork\Model || $assessment instanceof Quiz\Model) &&
                    $assessment->getWorkType() &&
                    \in_array($assessment->getWorkType()->getName(), $workTypeNames, true) &&
                    $visibility->isVisibleToUser($assessment, $viewee, true, null, !$this->access->isInstructor($this->getUser(), $folder))
                ) {
                    if (!isset($assessmentTasks[$folder->getId()])) {
                        $assessmentTasks[$folder->getId()] = new GenericCollection(IAssessable::class);
                    }

                    $assessmentTasks[$folder->getId()]->add($assessment);
                }

                $markbooksFolders[$folder->getId()]->add($assessment);
            }
        }

        // Sort the assessments
        $assessmentComparator = new AssessmentComparator($viewee);
        foreach ($assessmentTasks as $folderId => $assessments) {
            $assessments->sortByCallback($assessmentComparator->getCallable());
        }

        foreach ($markbooksFolders as $folderId => $assessments) {
            $assessment = $assessments->first();
            if($assessment instanceof IAssessable &&
                ($showOverallGrade || $this->access->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder()))
            ) {
                $markbooks->setOffset($folderId, new Markbook($assessments, $this->defaultGradeGroup, $this->getUser()));
            }
        }

        $yearUrl = $this->getYearUrl($viewee->getId());

        // Squish this mess out

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningGrades/summary.twig', [
            'student'         => $viewee,
            'term'            => $term,
            'year'            => $year,
            'terms'           => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'         => $folders,
            'assessmentTasks' => $assessmentTasks,
            'markbooks'       => $markbooks,
            'showOverallGrade' => $showOverallGrade,
            'mailHandle'      => $this->getSession()->get('mailhandle'),
            'currentUser'     => $this->getUser(),
            'yearUrl'          => $yearUrl
        ]));
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
     * @throws LogicException If cyclic graph detected
     * @throws UnexpectedValueException If unable to merge student collections
     */
    public function getGradesForSubject(User $viewee, Term\Model $term, Folder\Model $subject): void
    {
        $page        = FilterPage\Model::PAGE_GRADES;
        $route       = $this->getRouter()->generate('learningGrades.postReport', [
            'userId'    => $viewee->getId(),
            'termCode'  => $term->getCode(),
            'subjectId' => $subject->getId(),
        ]);
        $request     = $this->getRequest()->query->getArrayCopy();
        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, func_get_args(), $request);

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
                AssessmentFilters\TemporalState\Definition::KEY_CUSTOM_UPCOMING,
                AssessmentFilters\TemporalState\Definition::KEY_CUSTOM_OPEN,
                AssessmentFilters\TemporalState\Definition::KEY_CUSTOM_OVERDUE,
                AssessmentFilters\TemporalState\Definition::KEY_CUSTOM_CLOSED,
            ]
        ];
        $assessments = $this->findByFiltersWithParticipants($filters, $viewee, false);
        $markbook    = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());

        /* Find the teachers of this particular subject */
        $subject->loadTeachers(true);

        // Load conversation history
        $conversation = new Conversation($this->getConfigLoader(), $viewee, $subject);

        // Show the Current Grade Average for instructors and for students if the config var is on
        $isInstructor       = $this->access->isInstructor($this->getUser(), $subject);
        $canSeeGradeAverage = $isInstructor || $this->getConfigLoader()->get('show_overall_grade');

        // Get folders for drop-down.
        $folders = $this->findFoldersWithStudentInteractions($viewee, $term);

        $yearUrl = $this->getYearUrl($viewee->getId());
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
                'yearUrl'            => $yearUrl,
            ])->setTab('grade')->setTitle("Grades for {$subject->getTitle()}")
        );
    }

    /**
     * @param User $viewee
     * @param int $year
     * @throws ServiceNotFound If unable to find mail handler service
     * @throws \Schoolbox\Mapper\DefinitionException
     * @api   GET /learning/grades-year/{userId}/year/{year}
     * @level 165
     */
    public function getGradesYear(User $viewee, int $year): void
    {
        $from = (new \Alaress_Date_Format())->setYear($year)->startOfYear();
        $to = (new \Alaress_Date_Format())->setYear($year)->endOfYear();
        $assessments = $this->getAssessmentMapperProxy()->findAllByStudentBetweenDates($viewee, false, $from, $to);

        //If we are looking at the year, no term is specified.
        //Specify term 1 of that year in this case
        $dateString = "{$year}-01-01";
        $term = $this->getTermMapper()->findForDate(
            new \Alaress_Date_Format($dateString, \Alaress_Date_Format::DATABASE_DATE_FORMAT)
        );

        $filteredWorkTypes = WorkType\Mapper::getInstance()->findByColumn('show_on_grades_page', 1);

        $this->getCustomGrades($viewee, $assessments, $year, $term, $filteredWorkTypes);
    }

    /**
     * @param User         $viewee
     * @param Term\Model   $term
     * @param Folder\Model $subject
     * @throws InvalidArgumentException If unable to find the filter mapper class
     * @throws ReflectionException If the specified class does not exist
     * @throws DomainException If this class has not defined what entity it is filtering
     * @throws Filter\BrokenFilterException If a filter is unable to be processed
     * @throws ServiceNotFound If unable to locate HTML web Output
     * @throws DefinitionException If specified mapper join is not defined
     * @throws LogicException If cyclic graph detected during assessment translation
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws \Schoolbox\Mapper\Exception
     * @todo convert to a GET route
     */
    public function postReport(User $viewee, Term\Model $term, Folder\Model $subject): void
    {
        $request         = $this->request->post['filter_selection'] ?: [];
        $page            = FilterPage\Model::PAGE_GRADES;

        $codec           = new Codec(Filter\Mapper::getInstance(), Filter\Selection\Mapper::getInstance());
        $userSelections  = $codec->convertRequestToSelections($this->getUser(), $page, $request);

        // TODO don't save selections for non user-configurable page filters
        $this->selectionMapper->updateSelections($this->getUser(), $page, $userSelections);

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($userSelections, $this->getUser(), $page);

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
                AssessmentFilters\TemporalState\Definition::KEY_CUSTOM_UPCOMING,
                AssessmentFilters\TemporalState\Definition::KEY_CUSTOM_OPEN,
                AssessmentFilters\TemporalState\Definition::KEY_CUSTOM_OVERDUE,
                AssessmentFilters\TemporalState\Definition::KEY_CUSTOM_CLOSED,
            ]
        ]);

        // Show the graph for:
        // * instructors (always)
        // * students (if the config var is on, and their personal grade average setting is on)
        // We no longer show the grade graph at all if there's no comparative grades.
        $canSeeGraph = $this->access->isInstructor($this->getUser(), $subject)
            || ($viewee->getDisplayGradeGraph() && $this->getConfigLoader()
                                                        ->get('display_grade_graph'));

        $visibility = new Visibility($this->access);

        // Need to load other participants if we can see graph to get assessment mark statistics
        $assessments = $this
            ->findByFiltersWithParticipants($filters, $viewee, $canSeeGraph)
            ->filter(function (IAssessable $a) use ($visibility) {
                $viewer = $this->getUser();
                if ($visibility->isVisibleToUser($a, $viewer, true)) {
                    return true;
                }
                if ($viewer->getRole()->isParent()) {
                    foreach ($viewer->getChildren() as $child) {
                        if ($visibility->isVisibleToUser($a, $child, true)) {
                            return true;
                        }
                    }
                }
                return false;
            });

        // Only show the "send to showcase" trophy button to teachers and students, not parents
        $canSeeShowcaseButton = $this->getUser()->getRole()->isStaff() || $this->getUser()->getRole()->isStudent();

        // Let markbook calculate the statistics
        $filteredMarkbook = (new Markbook($assessments, $this->defaultGradeGroup, $this->getUser()))->sortAssessables(new AssessmentComparator($viewee));

        $graph = $canSeeGraph ? $this->buildGraph($filteredMarkbook, $viewee) : [];

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
                                              ->sortByCallback(function (Folder\Model $a, Folder\Model $b) {
                                                  return strnatcasecmp($a->getName(), $b->getName());
                                              });

        return $involvedFolders;
    }


    /**
     * Builds an array of data used by twig to render the assessment box-and-whiskers graph
     *
     * @param Markbook $markbook
     * @param User     $viewee
     * @return mixed[][] $graph
     * @var string     $graph ['info']         json encoded    // TODO Replace w/ object
     * @var string     $graph ['assessments']  json encoded
     * @var string     $graph ['data']         json encoded
     * @var string     $graph ['scatter']      json encoded
     */
    private function buildGraph(Markbook $markbook, User $viewee): array
    {
        // Get the data for the graph
        $graphInfo        = [];
        $graphAssessments = [];
        $graphData        = [];
        $graphScatter     = [];


        $statistics = $markbook->getAssessmentStatistics();

        foreach ($markbook->getAssessments() as $assessment) {
            if ($assessment->isFeedbackVisibleToUser($this->getUser()) && $assessment->getMarkScheme()->isQuantitative()) {
                $graphAssessments[] = $assessment->getTitle();

                $participant = $assessment->getParticipantIfExtant($viewee);

                $graphScatterValue = null;
                $graphInfoValue = [];
                if ($participant) {
                    // Set graph marker for student
                    $normalisedMark = $participant->getNormalisedMark(true);
                    if (null !== $normalisedMark) {
                        $graphScatterValue = (float)$normalisedMark;
                    }

                    // Set graph info value for modal
                    $feedback       = $participant->findAuthoritativeVisibleFeedback($this->getUser());
                    if ($feedback) {
                        $humanReadableMark = $feedback->getHumanReadableMark();
                        $comment           = $feedback->getComment();
                        if (!empty($humanReadableMark) || !empty($comment)) {
                            $graphInfoValue = [
                                'displayMark'  => $humanReadableMark,
                                'gradientMark' => $this->getConfigLoader()->get('grade_gradient_enable') ? (int)($feedback->getNormalisedMark() / 10) : 'none',
                                'info'         => $comment ?? '',
                            ];
                        }
                    } else {
                        //Projects don't have feedback so just get from participant record
                        $humanReadableMark = $participant->getHumanReadableMark(true);
                        if ($humanReadableMark) {
                            // Handle project mark, not available through feedback
                            $graphInfoValue = [
                                'displayMark'  => $humanReadableMark,
                                'gradientMark' => $this->getConfigLoader()->get('grade_gradient_enable') ? (int)($feedback->getNormalisedMark() / 10) : 'none',
                                'info'         => '',
                            ];
                        }
                    }
                }
                $graphScatter[] = $graphScatterValue;
                $graphInfo[] = $graphInfoValue;

                // Set box plot values
                $hasStats = $statistics[$assessment->getId()]['count'] > 0;
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
                            'value' => $statistics[$assessment->getId()]['quartile1'],
                        ],
                        // Median
                        [
                            'info'  => null,
                            'uid'   => null,
                            'value' => $statistics[$assessment->getId()]['quartile2'],
                        ],
                        // 3rd Quartile
                        [
                            'info'  => null,
                            'uid'   => null,
                            'value' => $statistics[$assessment->getId()]['quartile3'],
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
    protected function getLongitudinalRubricData(GenericCollection $assessments, User $student): array
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


    /**
     * @param array $filters
     * @param User  $viewee
     * @param bool  $loadOtherParticipantInteractions
     * @return GenericCollection|IAssessable[]|static
     * @throws DomainException If MarkType is invalid for the assessment, or user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws UnexpectedValueException If unable to merge student collections
     */
    private function findByFiltersWithParticipants(array $filters, User $viewee, bool $loadOtherParticipantInteractions): GenericCollection
    {
        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters);
        // Fetch assessments & participants
        $user        = $loadOtherParticipantInteractions ? null : $viewee;

        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $assessments = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, $user)
                                                   ->filter(function (IAssessable $a) use ($viewee) {
                                                       // Note that we the assessment list is already filtered to published assessments due to filters
                                                       return $a->isParticipant($viewee);
                                                   });
        return $assessments;
    }


    /* Getters/Setters
     */

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy(): AssessmentMapperProxy
    {
        return $this->assessmentMapperProxy;
    }


    /**
     * @return Term\Mapper
     */
    protected function getTermMapper(): Term\Mapper
    {
        return $this->termMapper;
    }

    /**
     * @param int $vieweeId
     * @return false|string|null
     */
    private function getYearUrl(int $vieweeId)
    {
        try{
            return $this->getRouter()->generate('learningGrades.getGradesYear', ['userId' => $vieweeId]);
        } catch (RouteNotFound $e) {
            return null;
        }
    }
}
