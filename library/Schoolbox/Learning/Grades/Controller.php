<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Grades;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use InvalidArgumentException;
use LogicException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Markbook\OverallDataset;
use Schoolbox\Assessment\StudentReportCard\Conversation;
use Schoolbox\Assessment\Term;
use Schoolbox\Assessment\Term\Model;
use Schoolbox\Collection;
use Schoolbox\Column\Decimal;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\User as ExternalUser;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilters;
use Schoolbox\Filter\Filters\Assessment\IdFinder;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Filter\Page as FilterPage;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\GenericCollection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Grades\Cohort\ClassResults;
use Schoolbox\Learning\Grades\Cohort\ValidUseCohortMarks;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\User;
use Twig\Error\Error as TwigError;
use TypeError;
use UnexpectedValueException;

use function func_get_args;

class Controller extends Base
{
    private ViewMode $viewMode;

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param Mapper $folderMapper
     * @param Term\Mapper $termMapper
     * @param RubricDataSource $rubricMapper
     * @param FilterSelection\Mapper $filterSelectionMapper
     * @param Access $access
     * @param IdFinder $assessmentIdFinder
     * @param IFactoryFactory $filterFactoryFactory
     * @param WorkType\Mapper $workTypeMapper
     * @param ExternalUser $externalUser
     * @param IConnector $internalDb
     * @param UserAccessMapper $userAccessMapper
     * @param Filter\Mapper $filterMapper
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
        private Folder\Mapper $folderMapper,
        private Term\Mapper $termMapper,
        private RubricDataSource $rubricMapper,
        private FilterSelection\Mapper $filterSelectionMapper,
        private Access $access,
        private AssessmentFilters\IdFinder $assessmentIdFinder,
        private Filter\IFactoryFactory $filterFactoryFactory,
        private WorkType\Mapper $workTypeMapper,
        ExternalUser $externalUser,
        private IConnector $internalDb,
        private UserAccessMapper $userAccessMapper,
        private Filter\Mapper $filterMapper,
    ) {
        $this->viewMode = new ViewMode($externalUser);
    }

    /**
     * Redirect user to their own grades page for the current term
     * @return void
     * @throws MapperException
     * @throws RouteNotFound
     */
    public function getGradesForCurrentUser(): void
    {
        /* For this student, find out (from their Year Level relationship) if they're in any folders
         * which might be inside an Extraordinary Term
         */
        if ($this->getUser()->getYear()) {
            $termCode = $this->termMapper->findForDateAndYearLevel(Alaress_Date_Format::now(), $this->getUser()->getYear())->getCode();
        } else {
            $termCode = $this->termMapper->findForToday()->getCode();
        }

        $this->response = $this->redirectFound(
            $this->routeGenerator->generate(
                'learningGrades.getGrades',
                [
                    'userId'   => $this->getUser()->getId(),
                    'termCode' => $termCode
                ]
            )
        );
    }

    /**
     * @param User $viewee
     * @param int  $year
     * @throws MapperException
     * @api   GET /learning/grades-year/{userId}/year/{year}
     * @level 165
     */
    public function getGradesYear(User $viewee, int $year): void
    {
        $from = (new Alaress_Date_Format())->setYear($year)->getStartOfSchoolYear();
        $to = (new Alaress_Date_Format())->setYear($year)->endOfSchoolYear();

        // Create new term for the full year summary.  Note this term will not exist in the database, so its ID will be null.
        // Functions that accept this fake term as a parameter cannot assume it has a non-null ID.
        $term = $this->termMapper->createModel()
            ->setTitle($from->getSchoolYear() . ' Year')
            ->setCode($from->getStartOfSchoolYear()->getYear() . '-full-year')
            ->setStart($from)
            ->setActualFinish($to);

        $this->getCustomGrades($viewee, $term, $from->getStartOfSchoolYear()->getYear());
    }

    /**
     * Display the grade summary page. With a list of classes/groups that have student interactions
     * for the specified Viewee and Term
     *
     * @param User       $viewee
     * @param Term\Model $term
     * @throws DefinitionException If a required mapper join is not defined
     * @throws TypeError If a PredicateBuilder method is called with wrong data type
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws LogicException If cyclic graph detected during translation
     * @throws UnexpectedValueException If unable to merge project participant collections
     * @throws MapperException
     */
    public function getGrades(User $viewee, Term\Model $term): void
    {
        $this->getCustomGrades($viewee, $term);
    }

    /**
     * @param User        $viewee
     * @param Model       $term
     * @param string|null $selectedYear
     * @return void
     * @throws TwigError
     * @throws MapperException
     * @throws RouteNotFound If unable to generate the customisation record route
     */
    private function getCustomGrades(User $viewee, Term\Model $term, ?string $selectedYear = null): void
    {
        $markSchemeFactory = new Factory($this->getConfigLoader());
        $gradeHelper       = new Helper(
            $this->folderMapper,
            $this->assessmentMapperProxy,
            $this->access,
            $markSchemeFactory
        );

        $requestViewMode = $this->requestQuery->get('viewMode');
        $viewModeContext = $this->viewMode->getViewModeContext(
            $this->getUser(),
            $viewee,
            is_string($requestViewMode) ? $requestViewMode : null
        );
        [$folders, $markbooks, $assessments] = $gradeHelper->generateGrades(
            $viewModeContext->effectiveViewer,
            $viewee,
            $term,
            $viewModeContext->canViewInstructorMarks
        );

        $filteredWorkTypes = WorkType\Mapper::getInstance()->findByColumn('show_on_grades_page', 1);
        $assessmentTasks   = (
            ($filteredWorkTypes->count() > 0) ?
            $gradeHelper->filterAssessments($viewModeContext->effectiveViewer, $assessments, $filteredWorkTypes, null) :
            new Collection()
        );

        /* Base URL for the view-mode switcher. It must round-trip to the same route that served this
         * page so the switcher preserves context. A year summary uses a fake term whose code is not
         * loadable by the term route, so for that case link back to the year route instead.
         */
        $currentRouteBase = $selectedYear !== null
            ? $this->routeGenerator->generate('learningGrades.getGradesYear', [
                'userId' => $viewee->getId(),
                'year'   => $selectedYear,
            ])
            : $this->routeGenerator->generate('learningGrades.getGrades', [
                'userId'   => $viewee->getId(),
                'termCode' => $term->getCode(),
            ]);

        /* A list of Terms to choose from
         *
         * If we're in an extraordinary Term right now, provide that as an option
         * in addition to the ordinary Terms
         */
        $this->response = $this->responseFromTemplate('@learningGrades/summary.twig', [
            'student'                => $viewee,
            'term'                   => $term,
            'year'                   => $selectedYear,
            'terms'                  => $this->termMapper->findAllWithExtraordinary($viewee),
            'folders'                => $folders,
            'assessmentTasks'        => $assessmentTasks,
            'markbooks'              => $markbooks,
            'mailHandle'             => $this->getSession()->get('mailhandle'),
            'currentUser'            => $this->getUser(),
            'yearUrl'                => $this->getYearUrl($viewee->getId()),
            'viewMode'               => $viewModeContext->viewModeValue->value ?? '',
            'viewModes'              => ViewMode::values(),
            'viewModeQuerySuffix'    => $viewModeContext->viewModeValue !== null
                ? '?viewMode=' . rawurlencode($viewModeContext->viewModeValue->value)
                : '',
            'effectiveViewer'        => $viewModeContext->effectiveViewer,
            'canViewInstructorMarks' => $viewModeContext->canViewInstructorMarks,
            'progressTicksReadOnly'  => $viewModeContext->progressTicksReadOnly,
            'currentRouteBase'       => $currentRouteBase,
            'customisationsRoute'    => $this->getRouteIfAuthorized(
                'customisationRecord.viewRecordByStudent',
                ['studentId' => $viewee->getId()]
            ) ?: null,
        ], '', 'grade');
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
     * @throws InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws DomainException If the filter FactoryFactory has not defined its filterable entity
     * @throws DefinitionException If specified mapper join is not defined
     * @throws Filter\BrokenFilterException If a injected filter is unable to be processed
     * @throws MapperException If a database column alias is incorrect
     * @throws LogicException If cyclic graph detected
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws TwigError
     */
    public function getGradesForSubject(User $viewee, Term\Model $term, Folder\Model $subject): void
    {
        $page = FilterPage\Model::PAGE_GRADES;
        $requestViewMode = $this->requestQuery->get('viewMode');
        $viewModeContext = $this->viewMode->getViewModeContext(
            $this->getUser(),
            $viewee,
            is_string($requestViewMode) ? $requestViewMode : null
        );
        $viewModeQuerySuffix = $viewModeContext->viewModeValue !== null
            ? '?viewMode=' . rawurlencode($viewModeContext->viewModeValue->value)
            : '';

        $route       = $this->routeGenerator->generate('learningGrades.postReport', [
            'userId'    => $viewee->getId(),
            'termCode'  => $term->getCode(),
            'subjectId' => $subject->getId(),
        ]) . $viewModeQuerySuffix;
        $request     = $this->requestQuery->getArrayCopy();
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
        $markSchemeFactory = new Factory($this->getConfigLoader());
        $overallMarkScheme = $markSchemeFactory->spawnForFolder(
            $subject,
            $viewModeContext->viewModeValue === ViewModeValue::STAFF
        );
        $markbook = new Markbook($assessments, $overallMarkScheme, $viewModeContext->effectiveViewer);

        /* Find the teachers of this particular subject */
        $subject->loadTeachers(true);

        // Load conversation history
        $conversation = new Conversation($this->getConfigLoader(), $viewee, $subject);

        // Get folders for drop-down.
        $folders = $this->folderMapper->findFoldersWithInteractionsForStudent($viewee, $term);
        $displayFolderIds = $this->folderMapper->filterIdsForGradePage($folders->getPropertyValues('id'), $term);
        $folders = $folders->filter(
            static function (Folder\Model $folder) use ($displayFolderIds) {
                return in_array($folder->getId(), $displayFolderIds);
            }
        );

        /* A list of Terms to choose from
         *
         * If we're in an extraordinary Term right now, provide that as an option
         * in addition to the ordinary Terms
         */
        $terms = $this->termMapper->findAllWithExtraordinary($viewee);

        $yearUrl = $this->getYearUrl($viewee->getId());

        $this->response = $this->responseFromTemplate(
            '@learningGrades/gradesOuter.twig',
            [
                'student'             => $viewee,
                'filterPanel'         => $filterPanel,
                'term'                => $term,
                'terms'               => $terms,
                'subject'             => $subject,
                'folders'             => $folders,
                'markbook'            => $markbook,
                'conversation'        => $conversation,
                'yearUrl'             => $yearUrl,
                'viewMode'            => $viewModeContext->viewModeValue->value ?? '',
                'viewModes'           => ViewMode::values(),
                'viewModeQuerySuffix' => $viewModeQuerySuffix,
                'effectiveViewer'     => $viewModeContext->effectiveViewer,
                'progressTicksReadOnly' => $viewModeContext->progressTicksReadOnly,
                'commentsReadOnly'      => $viewModeContext->commentsReadOnly,
            ],
            "Grades for {$subject->getTitle()}",
            'grade'
        );
    }

    /**
     * @param User         $viewee
     * @param Term\Model   $term
     * @param Folder\Model $subject
     * @throws InvalidArgumentException If unable to find the filter mapper class
     * @throws DomainException If this class has not defined what entity it is filtering
     * @throws Filter\BrokenFilterException If a filter is unable to be processed
     * @throws DefinitionException If specified mapper join is not defined
     * @throws LogicException If cyclic graph detected during assessment translation
     * @throws UnexpectedValueException If unable to merge student collections
     * @throws MapperException
     * @todo convert to a GET route
     */
    public function postReport(User $viewee, Term\Model $term, Folder\Model $subject): void
    {
        $request = $this->requestPost->get('filter_selection') ?: [];
        $page = FilterPage\Model::PAGE_GRADES;
        $requestViewMode = $this->requestQuery->get('viewMode');
        $viewModeContext = $this->viewMode->getViewModeContext(
            $this->getUser(),
            $viewee,
            is_string($requestViewMode) ? $requestViewMode : null
        );

        /* @var RosettaStone $l10n */
        $l10n = $this->getDi()->get('l10n');

        $codec           = new Codec(Filter\Mapper::getInstance(), Filter\Selection\Mapper::getInstance());
        $userSelections  = $codec->convertRequestToSelections($this->getUser(), $page, $request);

        // TODO don't save selections for non user-configurable page filters
        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $userSelections);

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
        // * all staff (always)
        // * students (if the config var is on, and their personal grade average setting is on)
        // We no longer show the grade graph at all if there's no comparative grades.
        $hasPermissionsAndSettings =
            $viewModeContext->viewModeValue === ViewModeValue::STAFF
            || ($viewee->getDisplayGradeGraph() && $this->isTabEnabled('graph', $subject));
        $hasDataToShow = false;

        $canSeeRubricGrade     = $this->isTabEnabled('descriptors', $subject);
        $canSeeCapabilityGrade = $this->isTabEnabled('capabilities', $subject);
        $canSeeProgression     = $this->isTabEnabled('progression', $subject);

        $visibility = new Visibility($this->access);

        // Need to load other participants if we can see graph to get assessment mark statistics
        $assessments = $this
            ->findByFiltersWithParticipants($filters, $viewee, $hasPermissionsAndSettings)
            ->filter(function (IAssessable $a) use ($visibility, $viewModeContext) {
                return $visibility->isVisibleToUser($a, $viewModeContext->effectiveViewer, true);
            });

        // Only show the "send to showcase" trophy button to teachers and students, not parents
        $canSeeShowcaseButton =
            $viewModeContext->effectiveViewer->getRole()->isStaff()
            || $viewModeContext->effectiveViewer->getRole()->isStudent();

        // Let markbook calculate the statistics
        $markSchemeFactory = new Factory($this->getConfigLoader());
        $overallMarkScheme = $markSchemeFactory->spawnForFolder(
            $subject,
            $viewModeContext->viewModeValue === ViewModeValue::STAFF
        );
        $filteredMarkbook = new Markbook($assessments, $overallMarkScheme, $viewModeContext->effectiveViewer)
            ->sortAssessables(new AssessmentComparator($viewee));

        /* If the config option lms_cohort_comparison is set,
         * instantiate a new CohortMarks instance
         *
         * Otherwise use the default Markbook instance
         *
         * These markbook instances provide the five-point summary for the graphs
         */
        $graph = [];
        $isCohort = false;

        /* All worktypes should show up here, for now. There are no specific admin controls to decide
         * which worktypes appear here: this means that non-statistical worktypes (e.g. for attendance
         * and other basic reporting) will still get plotted on the graph. You'd need something like the
         * checkboxes in Summative Reports to address this, or reuse the existing ones in admin somehow.
         */
        $workTypeIds = $this->workTypeMapper->findAll()->indexBy('id')->keys();

        if ($hasPermissionsAndSettings) {
            if ((new ValidUseCohortMarks())->isSatisfiedBy($this->getConfigLoader()->get('lms_cohort_comparison'), $subject)) {
                $isCohort = true;
                $graph = (new ClassResults(
                    $this->getConfigLoader()->get('grade_gradient_enable'),
                    $this->assessmentMapperProxy,
                    $assessments,
                    $viewModeContext->effectiveViewer,
                    $term,
                ))->collate($viewee, $workTypeIds);
            } else {
                $gradeHelper       = new Helper(
                    $this->folderMapper,
                    $this->assessmentMapperProxy,
                    $this->access,
                    $markSchemeFactory
                );

                /* All worktypes should show up here, for now. There are no specific admin controls to decide
                 * which worktypes appear here: this means that non-statistical worktypes (e.g. for attendance
                 * and other basic reporting) will still get plotted on the graph. You'd need something like the
                 * checkboxes in Summative Reports to address this, or reuse the existing ones in admin somehow.
                 */
                $workTypeIds = $this->workTypeMapper->findAll()->indexBy('id')->keys();

                $graph = $gradeHelper->buildGraph(
                    $filteredMarkbook,
                    $viewee,
                    $viewModeContext->effectiveViewer,
                    $this->getConfigLoader()->get(ConfigMapper::NAME_GRADE_GRADIENT_ENABLED),
                    $workTypeIds
                );
            }

            // return [
            //     'info'        => json_encode($graphInfo),
            //     'assessments' => json_encode($graphAssessments),
            //     'data'        => json_encode($graphData),
            //     'scatter'     => json_encode($graphScatter),
            // ];

            //if $graph has nothing to render
            //skip rendering the ui

            /* Check if the student has any results/grades and if not, don't show the tab / graph */
            /* If a student didn't have any meaningful results, don't render the graph
             * Having a set of data points being all null means you didn't get any results for your assessments
             * That's why we array_filter first
             */
            $hasDataToShow = count(array_filter($graph['scatter'], function ($item) {
                return $item !== null;
            })) > 0;
        }

        /* The Cohort Results graph is visible when two conditions are met:
         * you have the corresponding permissions and your shit is turn on;
         * AND that you have data to actually render
         *
         * You need both for the graph and tabs to actually display
         */
        $canSeeGraph = $hasPermissionsAndSettings && $hasDataToShow;

        /* These data structs inform the Capability Marks panel
         */
        $longitudinalAssessments = [];
        $longitudinalStudentMarks = [];

        if ($canSeeRubricGrade || $canSeeCapabilityGrade || $canSeeProgression) {
            $includeComparison = $viewModeContext->viewModeValue === ViewModeValue::STAFF || $viewee->getDisplayGradeGraph();

            [$longitudinalAssessments, $longitudinalStudentMarks] = $this->getLongitudinalRubricData(
                $assessments,
                $viewee,
                $viewModeContext->effectiveViewer,
                $includeComparison,
                $subject,
                $request
            );
        }

        /* Build tabs for grade report */
        $tabs = [];

        if ($canSeeGraph) {
            $tabs['graph'] = ['label' => $isCohort ? 'Cohort Results' : 'Class Results'];
        }

        $showRubricPoints = false;

        /* Only show Capability Marks, Learning Levels, Progression panels if there's something to meaningful to see
         */
        if (!empty($longitudinalStudentMarks)) {
            if ($canSeeRubricGrade) {
                $tabs['descriptors'] = ['label' => 'Learning Levels'];
                $showRubricPoints = $this->isTabEnabled('rubricPoints', $subject);
            }

            if ($canSeeCapabilityGrade) {
                $tabs['capabilities'] = ['label' => $l10n->translate('rubricGroup', false, true) . ' Marks'];
            }

            if ($canSeeProgression) {
                $tabs['progression'] = ['label' => 'Progression'];
            }
        }

        /* Select the first of any combination of those elements
         */
        $tabKeys     = array_keys($tabs);
        $selectedTab = array_shift($tabKeys);
        $tree        = [];

        // Layout assessments in a tree for twig to render
        foreach ($filteredMarkbook->getAssessments() as $assessment) {
            $id            = $assessment->getId();
            $context       = $assessment->getContext();
            $contextId     = $context->getId();
            $baseContextId = $assessment->getBaseContext()->getId();
            if (isset($tree[$baseContextId]) && $contextId !== $baseContextId) {
                // Ensure projects and their participation are always in the
                // base of the tree.
                // This means that a project's mark will always show,
                // even if it doesn't match the filter: but oh well.
                if ($context instanceof Project) {
                    $contexts = new GenericCollection(IAssessable::class);
                    $contexts->add($context);

                    // User's participation cannot be assumed: they cannot be assumed to be in the folder
                    $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection(
                        collection: $contexts,
                        users: (new \Schoolbox\Collection())->add($viewee),
                        removeExcluded: false,
                        assumeParticipation: false
                    );
                    $tree[$baseContextId]['assessments'][$contextId]['assessment'] = $context;
                }
                $tree[$baseContextId]['assessments'][$contextId]['context']          = $context;
                $tree[$baseContextId]['assessments'][$contextId]['assessments'][$id] = $assessment;
            } else {
                $tree[$contextId]['context']                        = $context;
                $tree[$contextId]['assessments'][$id]['assessment'] = $assessment;
            }
        }

        $this->response = $this->responseAsJson([
            'status'  => 200,
            'content' => $this->responseFromTemplateUnthemed('@learningGrades/gradesInner.twig', [
                'student'               => $viewee,
                'markbook'              => $filteredMarkbook,
                'tree'                  => $tree,
                'tabs'                  => $tabs,
                'selectedTab'           => $selectedTab,
                'canSeeShowcaseButton'  => $canSeeShowcaseButton,
                'canViewInstructorMarks' => $viewModeContext->canViewInstructorMarks,
                'effectiveViewer'       => $viewModeContext->effectiveViewer,
                'graph'                 => $graph,
                'showRubricPoints'      => $showRubricPoints,
                'progressTicksReadOnly' => $viewModeContext->progressTicksReadOnly,
                'transitions'           => (
                    $viewModeContext->progressTicksReadOnly ? [] : ViewMode::tickTransitions(
                        $viewModeContext->viewModeValue,
                        $this->access->isInstructor($viewModeContext->effectiveViewer, $subject)
                    )
                ),
                'returnUrlSuffix'       => '?' . Redirect::RETURN_URL . '=' . Redirect::encode($_SERVER['REQUEST_URI']),
                /* Capability Marks panel
                 */
                'longitudinalAssessments' => $longitudinalAssessments,
                'longitudinalStudentMarks' => $longitudinalStudentMarks,
            ])->getBody()->getContents(),
        ]);
    }

    /**
     * @param string       $tabName
     * @param Folder\Model $folder
     * @return bool
     */
    private function isTabEnabled(string $tabName, Folder\Model $folder): bool
    {
        switch ($tabName) {
            case 'graph':
                $configName   = 'display_grade_graph';
                $propertyName = 'displayAssessmentGrade';
                break;
            case 'descriptors':
                $configName   = 'enable_grades_criteria';
                $propertyName = 'displayRubricGrade';
                break;
            case 'rubricPoints': // this is not a tab, but...
                $configName   = 'rubric_show_points';
                $propertyName = 'showRubricPoints';
                break;
            case 'capabilities':
                $configName   = 'grade_capability_display';
                $propertyName = 'displayCapabilityGrade';
                break;
            case 'progression':
                $configName   = 'grade_progression_display';
                $propertyName = 'displayProgressionGrade';
                break;
            default:
                return false;
        }

        /**
         * Logic
         * LMS setting set to off and no year level on the class page - do not show.
         * LMS setting set to on and no year level on the class page - show.
         * LMS setting set to off and year level on the class page to on - show.
         * LMS setting set to off and year level on the class page to off - do not show.
         * LMS setting set to off and clash on year levels (one says off the other on) - do not show.
         * LMS setting set to on and clash on year levels (one says off the other on) - show.
         */

        // Has no Year level on folder: use LMS setting
        if (0 === $folder->getYearLevels()->count()) {
            return $this->getConfigLoader()->get($configName);
        }

        // get options from folder year level
        $propertyValues = array_unique($folder->getYearLevels()->getPropertyValues($propertyName));

        // if the folders yearlevels have the requested setting as different values (some on / some off)
        // use LMS setting
        if (2 <= count($propertyValues)) {
            return $this->getConfigLoader()->get($configName);
        }

        // year levels all agree on the setting, use year level setting
        return $propertyValues[0];
    }

    /**
     * @param GenericCollection<IAssessable> $activities
     * @param User $student
     * @param User $viewer Effective viewer for feedback visibility checks
     * @param bool $includeComparison Include other students results for comparison
     * @param Folder\Model $folder
     * @param array $request
     * @return array
     * @throws MapperException
     * @throws RouteNotFound
     */
    private function getLongitudinalRubricData(
        GenericCollection $activities,
        User $student,
        User $viewer,
        bool $includeComparison,
        Folder\Model $folder,
        array $request
    ): array {
        // Filter to activities with marks visible to the selected view mode.
        $activitiesWithVisibleMarks = $activities->filter(function (IAssessable $assessment) use ($viewer) {
            return $assessment->isFeedbackVisibleToUser($viewer);
        });

        /* Attempt to process statistics for Cohort aggregation for each capability/indicator/descriptor
         * but...there's a problem I can't solve
         *
         * Disabled this for now if/until we figure it out
         */
//        if ((new ValidUseCohortMarks())->isSatisfiedBy($this->getConfigLoader()->get('lms_cohort_comparison'), $folder)) {
//            return (new Progression(
//                $this->getDi()->get('databaseInternal'),
//                $this->assessmentMapperProxy,
//                $dueWorksWithVisibleMarks,
//                $includeComparison
//            ))->collate($student);
//        }

        [
            $assessments,
            $exclusions,
            $studentMarks,
            $overallMarks
        ] = (new OverallDataset(
            $this->internalDb,
            new Decimal(),
            new MarkScheme\TotalMarkFractional(),
            new \Schoolbox\Learning\MarkScheme\Factory($this->getConfigLoader()),
            $this->filterMapper,
            $this->userAccessMapper,
            $this->routeGenerator
        ))->buildMarkbookData($folder, $request, $activitiesWithVisibleMarks->indexBy('id')->keys());

        /* We only need data for this one particular student
         */
        $data = $studentMarks[$student->getId()] ?? null;

        /* Student doesn't have any data; exit earlyish
         */
        if (null === $data) {
            return [];
        }

        /* Transpose this rubric markbook data
         */
        $stack = [];

        foreach ($data['gridData'] as $assessmentId => $rubricInstanceData) {
            foreach ($rubricInstanceData as $rubricInstanceId => $rubricGridData) {
                foreach ($rubricGridData['capabilities'] as $capabilityId => $capabilityData) {
                    if (!isset($stack[$capabilityId])) {
                        $stack[$capabilityId] = [
                            'name' => $capabilityData['name'],
                            /* We only care about published data here; these are results for a student indexed by
                             * their assessment id and grid id
                             */
                            'assessmentResults' => [],
                            'indicators' => [],
                        ];
                    }

                    /* Chuck in this student's result, indexed by assessment identifier
                     */
                    $stack[$capabilityId]['assessmentResults'][$assessmentId]['grid'][$rubricInstanceId]['published'] = $capabilityData['published'];

                    foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                        if (!isset($stack[$capabilityId]['indicators'][$indicatorId])) {
                            $stack[$capabilityId]['indicators'][$indicatorId] = [
                                'name' => $indicatorData['name'],
                                /* We only care about published data here; these are results for a student indexed by
                                 * the assessment id and contains descriptor values
                                 */
                                'assessmentResults' => [],
                            ];
                        }

                        /* Chuck in this student's result, indexed by assessment identifier
                         */
                        $stack[$capabilityId]['indicators'][$indicatorId]['assessmentResults'][$assessmentId]['grid'][$rubricInstanceId]['published'] = $indicatorData['published'];
                    }
                }
            }
        }

        /* Sort capabilities alphabetically
         */
        uasort($stack, static function ($a, $b) {
            return $a['name'] <=> $b['name'];
        });

        /* Sort indicators alphabetically
         */
        foreach ($stack as $capabilityId => &$capability) {
            uasort($capability['indicators'], static function ($a, $b) {
                return $a['name'] <=> $b['name'];
            });
        }

        /* The $assessments node is an array of assessment metadata which we can use to make the heading row; each
         * node in $assessments knows about its grid settings so we can make a hash lookup to quickly build this table:
         * foreach capability in the stack, process each assessment's result.
         *
         * $stack eventually becomes known as $longitudinalStudentMarks once we leave this method
         */
        return [
            $assessments,
            $stack,
        ];
    }

    /**
     * @param array $filters
     * @param User  $viewee
     * @param bool  $loadOtherParticipantInteractions
     * @return GenericCollection<IAssessable>
     * @throws DomainException If MarkType is invalid for the assessment, or user has interaction but not participant record
     * @throws InvalidArgumentException If assessment class not registered
     * @throws LogicException If cyclic graph detected
     * @throws DefinitionException If unable to locate a specified mapper join
     * @throws UnexpectedValueException If unable to merge student collections
     */
    private function findByFiltersWithParticipants(array $filters, User $viewee, bool $loadOtherParticipantInteractions): GenericCollection
    {
        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
        // Fetch assessments & participants
        $user        = $loadOtherParticipantInteractions ? null : $viewee;

        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);

        // User's participation cannot be assumed: they cannot be assumed to be in the folder
        $assessments = $this->assessmentMapperProxy
            ->loadParticipantsIntoAssessmentCollection(collection: $assessments, users: $user ? (new \Schoolbox\Collection())->add($user) : null, removeExcluded: false, assumeParticipation: false)
            ->filter(static function (IAssessable $a) use ($viewee) {
                // Note that we the assessment list is already filtered to published assessments due to filters
                return $a->isParticipant($viewee);
            });
        return $assessments;
    }

    /**
     * @param int $vieweeId
     * @return string|null
     */
    private function getYearUrl(int $vieweeId): ?string
    {
        try {
            return $this->routeGenerator->generate('learningGrades.getGradesYear', ['userId' => $vieweeId]);
        } catch (RouteNotFound $e) {
            return null;
        }
    }
}
