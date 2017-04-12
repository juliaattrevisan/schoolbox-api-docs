<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning;

use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Mark;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\StudentReportCard\Conversation;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\PendingFactory;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Statistics;
use Schoolbox\Learning\Classes\ClassList;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;
use Timetable_ColourMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Assessment\Quiz\Model as AssessmentQuiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use \Alaress_Date_Format;
use Schoolbox\Filter;
use Schoolbox\Filter\Option as FilterOption;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\Mapper;
use Schoolbox\Filter\Filters\Folder as FolderFilter;

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
     * @var TermMapper
     */
    private $termMapper;

    /**
     * @var GradeGroup
     */
    private $defaultGradeGroup;

    /**
     * @var FolderMapper
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

    /** @var  \External_ClassInterface */
    private $externalClass;

    /**
     * @var Access
     */
    private $access;

    /**
     * @var IConnector
     */
    private $database;

    /** @var FilterSelection\Mapper */
    private $filterSelectionMapper;


    /**
     * @param ErrorMapper                $errorMapper
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param FolderMapper               $folderMapper
     * @param TermMapper                 $termMapper
     * @param GradeGroup                 $defaultGradeGroup
     * @param ShowcaseMapper             $showcaseMapper
     * @param RubricMapper               $rubricMapper
     * @param QuizMapper                 $quizMapper
     * @param QuizAttemptMapper          $quizAttemptMapper
     * @param Timetable_ColourMapper     $timetableColourMapper
     * @param CourseImplementationMapper $courseImplementationMapper
     * @param CourseSpecificationMapper  $courseSpecificationMapper
     * @param FilterSelection\Mapper     $filterSelectionMapper
     * @param \External_ClassInterface   $externalClass
     * @param IConnector                 $database
     * @param Access                     $access
     */
    public function __construct(
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        FolderMapper $folderMapper,
        TermMapper $termMapper,
        GradeGroup $defaultGradeGroup,
        ShowcaseMapper $showcaseMapper,
        RubricMapper $rubricMapper,
        QuizMapper $quizMapper,
        QuizAttemptMapper $quizAttemptMapper,
        Timetable_ColourMapper $timetableColourMapper,
        CourseImplementationMapper $courseImplementationMapper,
        CourseSpecificationMapper $courseSpecificationMapper,
        FilterSelection\Mapper $filterSelectionMapper,
        \External_ClassInterface $externalClass,
        IConnector $database,
        Access $access
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
        $this->courseImplementationMapper = $courseImplementationMapper;
        $this->courseSpecificationMapper  = $courseSpecificationMapper;
        $this->filterSelectionMapper      = $filterSelectionMapper;
        $this->externalClass              = $externalClass;
        $this->database                   = $database;
        $this->access                     = $access;
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
     * @param User                 $viewee The user whose pending assessments we are viewing.
     * @param Filter\FactoryFactory $filterFactoryFactory
     * @throws RouteNotFound
     * @throws ServiceNotFound
     * @throws \InvalidArgumentException
     * @throws Mapper\Exception
     * @todo  split pending assessments into own controller
     */
    public function getPendingAssessmentsForUser(User $viewee, Filter\FactoryFactory $filterFactoryFactory)
    {

        // Generate a list of classes/groups that the viewee teaches
        $getSubjects = true;
        $groupAccessLevel = UserAccess::ACCESS_WRITE;
        $folders = $this->folderMapper->findForUser($viewee, $getSubjects, $groupAccessLevel);
        $folderRunTimeOptions = new FolderFilter\RunTimeOptions($folders);

        /* TODO - Each definition defines the pages where it appears.
         * Consider making the factoryFactory build this filterComponentCollection, passing in
         * a single $runTimeOptions array, indexed by filterDefinition::class
         */

        // Build Filter Panel Components
        $termFilterComponent     = $filterFactoryFactory->buildTermFilterFactory()->buildComponent($this->getUser(), FilterOption\Model::PAGE_PENDING_ASSESSMENTS);
        $assessmentTypeComponent = $filterFactoryFactory->buildAssessmentTypeFilterFactory()->buildComponent($this->getUser(), FilterOption\Model::PAGE_PENDING_ASSESSMENTS);
        $workTypeComponent       = $filterFactoryFactory->buildWorkTypeFilterFactory()->buildComponent($this->getUser(), FilterOption\Model::PAGE_PENDING_ASSESSMENTS);
        $folderComponent         = $filterFactoryFactory->buildFolderFilterFactory()->buildComponent($this->getUser(), FilterOption\Model::PAGE_PENDING_ASSESSMENTS, $folderRunTimeOptions);
        $weightingComponent      = $filterFactoryFactory->buildWeightingFilterFactory()->buildComponent($this->getUser(), FilterOption\Model::PAGE_PENDING_ASSESSMENTS);

        // Combine components
        $filterComponents =  $filterFactoryFactory->createFilterComponentCollection([
            $termFilterComponent,
            $assessmentTypeComponent,
            $workTypeComponent,
            $weightingComponent,
            $folderComponent,
        ]);

        $route       = $this->getRouter()->generate('learning.postPendingAssessmentsForUser', ['userId' => $viewee->getId()]);
        $filterPanel = new Filter\Panel($route, $filterComponents);

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/pendingAssessmentsOuter.twig', [
            'viewee'      => $viewee,
            'filterPanel' => $filterPanel,
        ])->setTab('submission'));
    }


    /**
     * Displays a list of Assessments that require marking, and Assessments currently unsubmitted
     *
     * @api   GET /learning/pending/:userId
     * @level 165
     * @param User                 $viewee The user whose pending assessments we are viewing.
     * @param Filter\FactoryFactory $filterFactoryFactory
     * @var  string[][]            $_POST  ['filter_selection'] Indexed by filter.id, then concrete key
     * @throws \LogicException
     * @throws \DomainException
     * @throws \OutOfRangeException
     * @throws ServiceNotFound
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function postPendingAssessmentsForUser(User $viewee, Filter\FactoryFactory $filterFactoryFactory)
    {
        try {
            $postFilterSelections = $this->request->post['filter_selection'] ?: [];

            $content = $this->generatePendingAssessmentReport($viewee, $filterFactoryFactory, $postFilterSelections);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $content,
            ]));

        } catch (\Exception $e){
            $this->returnJsonError($e);
        } catch (\Throwable $e){
            $this->returnJsonError($e);
        }
    }


    /**
     * Generate a pending assessment report as HTML string
     *
     * @param User                  $viewee
     * @param Filter\FactoryFactory $filterFactoryFactory
     * @param string[][]            $postFilterSelections - Indexed by filter.id, then concrete key
     * @return string
     * @throws \InvalidArgumentException
     * @throws \DomainException
     * @throws \LogicException
     */
    protected function generatePendingAssessmentReport(
        User $viewee,
        Filter\FactoryFactory $filterFactoryFactory,
        array $postFilterSelections
    ) {
        // What filters are in use on this page? Get their key converters
        $keyConverters = $filterFactoryFactory->createKeyConverterCollection();

        // Term Filter
        $termKeyConverter = $filterFactoryFactory->buildTermFilterFactory()->buildKeyConverter();
        $keyConverters->offsetSet($termKeyConverter->getFilter()->getId(), $termKeyConverter);

        // AssessmentType Filter
        $assessmentTypeKeyConverter = $filterFactoryFactory->buildAssessmentTypeFilterFactory()->buildKeyConverter();
        $keyConverters->offsetSet($assessmentTypeKeyConverter->getFilter()->getId(), $assessmentTypeKeyConverter);

        // WorkType Filter
        $workTypeKeyConverter = $filterFactoryFactory->buildWorkTypeFilterFactory()->buildKeyConverter();
        $keyConverters->offsetSet($workTypeKeyConverter->getFilter()->getId(), $workTypeKeyConverter);

        // FolderFilter
        $folderKeyConverter = $filterFactoryFactory->buildFolderFilterFactory()->buildKeyConverter();
        $keyConverters->offsetSet($folderKeyConverter->getFilter()->getId(), $folderKeyConverter);

        // Weighting
        $weightingKeyConverter = $filterFactoryFactory->buildWeightingFilterFactory()->buildKeyConverter();
        $keyConverters->offsetSet($weightingKeyConverter->getFilter()->getId(), $weightingKeyConverter);

        // Save
        $page                = FilterOption\Model::PAGE_PENDING_ASSESSMENTS;
        $selectionsPerFilter = $this->filterSelectionMapper->updateSelections($keyConverters, $this->getUser(), $page, $postFilterSelections);


        // Get selections, defaulting to full possible set if filter not present in panel (ie disabled by admin)
        $selectedTerms           = $termKeyConverter->getSelectedItems($selectionsPerFilter[$termKeyConverter->getFilter()->getId()]);
        $selectedWorkTypes       = $workTypeKeyConverter->getSelectedItems($selectionsPerFilter[$workTypeKeyConverter->getFilter()->getId()]);
        $selectedAssessmentTypes = $assessmentTypeKeyConverter->getSelectedItems($selectionsPerFilter[$assessmentTypeKeyConverter->getFilter()->getId()]);
        $selectedFolderIds       = $folderKeyConverter->getSelectedItems($selectionsPerFilter[$folderKeyConverter->getFilter()->getId()]);
        $selectedWeightings      = $weightingKeyConverter->getSelectedItems($selectionsPerFilter[$weightingKeyConverter->getFilter()->getId()]);

        // If currentUser == viewee, use currentUser as it may already have class codes
        // attached and avoid the need for an external DB call.
        if ($this->getUser()->getId() === $viewee->getId()) {
            $viewee = $this->getUser();
        }

        $toChaseLimit = \Alaress_Date_Format::now()->addDays(1);

        $factory     = new PendingFactory(
            $viewee,
            $this->database,
            $this->externalClass,
            $selectedTerms,
            $selectedWorkTypes,
            $selectedWeightings,
            $selectedFolderIds,
            $toChaseLimit,
            $selectedAssessmentTypes,
            $this->folderMapper
        );
        $assessments = $factory->getPendingAssessments();

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
            'status'    => 500,
            'code'      => $e->getCode(),
            'message'   => $e->getMessage(),
            'line'      => $e->getLine(),
            'file'      => $e->getFile(),
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
            'terms'         => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'hideTimetable' => $hideTimetable,
        ]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/classes.twig', $gridData)->setTab('classes'));
    }


    /**
     * Displays a list of grades/results for the specified user and term for all subjects
     *
     * @api   GET /learning/grades/{userId}{/termCode}
     * @level 165
     * @param User $viewee
     * @param  Term $term
     * @throws ServiceNotFound If unable to find mail handler service
     * @throws DefinitionException
     * @throws MapperException
     */
    public function getGrades(User $viewee, Term $term)
    {
        // Regardless of whether the term is current, only show folders where the student
        // has responses or feedback

        // Add folders with response or feedback
        /** @var GenericCollection|Folder[] $folders */
        $folders = $this->folderMapper->createCollection()
            ->mergeUnique($this->folderMapper->findFoldersWithFeedbackToStudent($viewee, $term), 'id')
            ->mergeUnique($this->folderMapper->findFoldersWithResponses($viewee, $term), 'id')
            ->sortBy('name', 'ASC');

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

        // Generate markbooks for these assessments
        /** @var GenericCollection|Markbook[] Indexed by folder.id */
        $markbooks = new GenericCollection(Markbook::class);

        foreach ($assessmentsPerFolder as $folderId => $assessments) {
            $markbooks->setOffset($folderId, new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser()));
        }

        // Squish this mess out

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/grades.twig', [
            'student'    => $viewee,
            'term'       => $term,
            'terms'      => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'    => $folders,
            'markbooks'  => $markbooks,
            'mailHandle' => $this->getSession()->get('mailhandle'),
        ])->setTab('grade'));
    }


    /**
     * Displays a list of grades for the specified user, term and subject
     *
     * @api   GET /learning/grades/{userId}/{termCode}/{subjectId}
     * @level 165
     * @param User   $viewee
     * @param Term   $term
     * @param Folder $subject
     * @throws DefinitionException
     * @throws MapperException
     * @throws ServiceNotFound
     * @throws \InvalidArgumentException
     */
    public function getGradesForSubject(User $viewee, Term $term, Folder $subject)
    {
        // Despite the fact that this is displaying grades for a single student,
        // ALL participants are still required (because the student is compared
        // against all other participants).
        $assessments = $this->getAssessmentMapperProxy()->findAllByFolderIdForReview(
            $subject->getId(),
            null,
            [
                'term'  => $term,
                'order' => ['due_date' => 'asc'],
            ]
        );

        /** @var GenericCollection|IAssessable[] $assessments */
        $assessments = $assessments->filter(function (IAssessable $activity) {
            return $activity->isPublished();
        });

        // Instructors can see the graph regardless of the config settings.
        $isInstructor = $this->getAccess()->isInstructor($this->getUser(), $subject);

        // Show the box/whisker plot for instructors and for students if the config var is on
        $canSeeBoxPlot = $isInstructor || $this->getConfigLoader()->get('display_grade_average');

        // Show the graph for instructors and for students if the config var is on
        $canSeeGraph = $isInstructor || $this->getConfigLoader()->get('display_grade_graph');

        // Show the Current Grade Average for instructors and for students if the config var is on
        $canSeeGradeAverage = $isInstructor || $this->getConfigLoader()->get('show_overall_grade');

        // Let markbook calculate the statistics
        $markbook   = new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser());
        $statistics = $markbook->getAssessmentStatistics();

        // Get the data for the graph
        $graphInfo        = [];
        $graphAssessments = [];
        $graphData        = [];
        $graphScatter     = [];
        $graphFeedback    = [];
        $quizzes          = [];

        foreach ($assessments as $assessment) {

            // If this is a quiz we need to find out if the student has made any attempts so we can display a link to view them

            if ($assessment instanceof AssessmentQuiz) {
                try {
                    $quiz     = $this->quizMapper->findOneByColumn('assessment_id', $assessment->getId());
                    $attempts = $this->quizAttemptMapper->findByColumnList([
                        'assess_id' => $quiz->getId(),
                        'user_id'   => $viewee->getId(),
                    ]);

                    if ($attempts->count() > 0) {
                        $quizzes[$assessment->getId()] = $quiz;
                    }
                } catch (MapperException $e) {
                }
            }

            $graphAssessments[] = $assessment->getTitle();

            $feedback = null;
            if ($assessment->isParticipant($viewee)) {
                $feedback = $assessment->getParticipant($viewee)->findMostRecentVisibleFeedback($this->getUser());
            }

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

            if ($hasStats && $canSeeBoxPlot) {
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

        // Keep a collection of all folders student has an assessment in for use in the dropdown

        $folders = $this->folderMapper
            ->findFoldersWithFeedbackToStudent($viewee, $term)
            ->mergeUnique($this->folderMapper->findFoldersWithResponses($viewee, $term), 'id');

        // Grab the students conversation history (this also implicitly loads teachers)
        $conversation = new Conversation($this->getConfigLoader(), $viewee, $subject);

        // Squeeze it on out

        $returnUrlSuffix = '?' . Redirect::RETURN_URL . '=' . Redirect::encode($_SERVER['REQUEST_URI']);

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/gradesSubject.twig', [
            'student'            => $viewee,
            'term'               => $term,
            'terms'              => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'            => $folders,
            'subject'            => $subject,
            'markbook'           => $markbook,
            'graphShow'          => $canSeeGraph,
            'graphInfo'          => json_encode($graphInfo),
            'graphAssessments'   => json_encode($graphAssessments),
            'graphData'          => json_encode($graphData),
            'graphScatter'       => json_encode($graphScatter),
            'graphFeedback'      => $graphFeedback,
            'canComment'         => $conversation->canComment($this->getUser()),
            'comments'           => $conversation->getVisibleComments($this->getUser()),
            'quizzes'            => $quizzes,
            'returnUrlSuffix'    => $returnUrlSuffix,
            'longitudinalRubric' => $this->getLongitudinalRubricData($subject, $viewee, $term),
            'canSeeGradeAverage' => $canSeeGradeAverage,
        ]));
    }


    /**
     * @param Folder $subject
     * @param User   $student
     * @param Term   $term
     * @return array
     */
    protected function getLongitudinalRubricData(Folder $subject, User $student, Term $term)
    {
        if (!$this->getConfigLoader()->get('enable_grades_criteria')) {
            // only populate this if we're actually allowed to see it
            return [];
        }

        return $this->getRubricMapper()->getLongitudinal($subject, $student, $term->getStart(), $term->getActualFinish());
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
     * @return TermMapper
     */
    protected function getTermMapper()
    {
        return $this->termMapper;
    }


    /**
     * @param TermMapper $termMapper
     * @return static
     */
    protected function setTermMapper(TermMapper $termMapper)
    {
        $this->termMapper = $termMapper;
        return $this;
    }


    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }


    /**
     * @param FolderMapper $folderMapper
     * @return static
     */
    protected function setFolderMapper(FolderMapper $folderMapper)
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
