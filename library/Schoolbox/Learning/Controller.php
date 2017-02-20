<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning;

use Alaress\Paginator\Paginator;
use Alaress\Paginator\UrlPaginator;
use Alaress\Web\Controller\Base;
use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\StudentReportCard\Conversation;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\PendingFactory;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;
use Timetable_ColourMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Assessment\Quiz\Model as AssessmentQuiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;

class Controller extends Base
{
    // Enable pagination of both part1 and part2 of the screen, with this many items per page.
    const COUNT_ASSESSMENTS_PER_PAGE = 100;

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

    /** @var  \External_ClassInterface */
    private $externalClass;

    /**
     * @var \Database
     */
    private $database;

    /**
     * Controller constructor.
     *
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
     * @param \External_ClassInterface   $externalClass
     * @param \Database                  $database
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
        \External_ClassInterface $externalClass,
        \Database $database
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
        $this->externalClass              = $externalClass;
        $this->database                   = $database;
    }

    /**
     * Redirects to current user
     *
     * @api   GET /learning/pending
     * @level 165
     */
    public function getPendingAssessments()
    {
        // Is current user not SuperUser or Staff ?
        if (!($this->getUser()->isSuperuser() || $this->getUser()->getRole()->isStaff())) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learningUpcoming.getUpcomingForStudent', [
                'studentId' => $this->getUser()->getId()
            ]));
        }

        // If no staff explicitly requested, redirect to current user
        $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getPendingAssessmentsForUser', [
            'userId' => $this->getUser()->getId(),
            'viewee' => $this->getUser(),
        ]));
    }

    /**
     * Displays a list of Assessments that require marking, and Assessments currently unsubmitted
     *
     * @api   GET /learning/pending/:userId
     * @level 165
     * @param User $viewee The user whose pending assessments we are viewing.
     */
    public function getPendingAssessmentsForUser(User $viewee = null)
    {
        // No User found ?
        if(is_null($viewee)){
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
        }

        // Only display from Staff perspective.
        if(!$viewee->getRole()->isStaff()){
            // Is the Viewee as student or parent?
            if($viewee->getRole()->isStudent() || $viewee->getRole()->isParent()) {
                // Redirect to 'Due Assessments' page
                $this->getResponse()->redirect->to($this->getRouter()->generate('learningUpcoming.getUpcomingForStudent', [
                    'studentId' => $viewee->getId()
                ]));
                return;
            }
            // Must be a Guest. Block them.
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        }

        // If currentUser == viewee, use currentUser as it may already have class codes
        // attached and avoid the need for an external DB call.
        if($this->getUser()->getId() == $viewee->getId()){
            $viewee = $this->getUser();
        }

        $term = $this->termMapper->findForToday();
        $toChaseLimit = \Alaress_Date_Format::now()->addDays(1);

        // Should we include tasks?
        $includeTasks = $this->getConfigLoader()->get('pending_assessment_types');

        $factory = new PendingFactory($viewee, $this->database, $this->externalClass, $term, $toChaseLimit, $includeTasks, $this->folderMapper);
        $assessments = $factory->getPendingAssessments();

        /** @var Collection|IAssessable[] $assessmentsToMark */
        $assessmentsToMark = $this->assessmentMapperProxy->createCollection();
        foreach($assessments as $assessment){
            if($assessment->numMarkingRequired()){
                $assessmentsToMark->add($assessment);
            }
        }
        $assessmentsToMark->sortBy('dueAt', 'asc');

        /** @var IAssessable[] $assessmentsToMark */
        $assessmentsToChase = $this->assessmentMapperProxy->createCollection();

        foreach($assessments as $assessment){
            if($assessment->getDueAt()->lt($toChaseLimit) && $assessment->numNoResponseOrFeedback()){
                $assessmentsToChase->add($assessment);
            }
        }
        $assessmentsToChase->sortBy('dueAt', 'asc');

        $path = $this->request->url->get(PHP_URL_PATH);
        $partOnePage = $this->request->query->get('p1', 1);
        $partTwoPage = $this->request->query->get('p2', 1);
        $url = "$path?p1=$partOnePage&p2=$partTwoPage";

        // Pagination
        $partOnePaginator = (new UrlPaginator())
            ->setPaginator((new Paginator())->setTotal(count($assessmentsToMark))->setPageSize(self::COUNT_ASSESSMENTS_PER_PAGE))
            ->setUrl($url)
            ->setParam('p1');
        $partTwoPaginator = (new UrlPaginator())->setPaginator(
            (new Paginator())->setTotal(count($assessmentsToChase))->setPageSize(self::COUNT_ASSESSMENTS_PER_PAGE))
            ->setUrl($url)
            ->setParam('p2');

        // Did user request a page that does not exist?
        if($partOnePage < 0 || ($partOnePaginator->getPageCount() && $partOnePage > $partOnePaginator->getPageCount())){
            throw new \DomainException("Invalid part one page: $partOnePage");
        }
        if($partTwoPage < 0 || ($partTwoPaginator->getPageCount() && $partTwoPage > $partTwoPaginator->getPageCount())){
            throw new \DomainException("Invalid part two page: $partTwoPage");
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/pendingAssessments.twig', [
            'viewee'             => $viewee,
            'assessmentsToMark'  => $assessmentsToMark,
            'assessmentsToChase' => $assessmentsToChase,
            'partOnePaginator'   => $partOnePaginator,
            'partOnePage'        => $partOnePage,
            'partTwoPaginator'   => $partTwoPaginator,
            'partTwoPage'        => $partTwoPage,
        ])->setTab('submission'));
    }

    /**
     * Displays a list of classes for the current logged in user
     *
     * If the term includes now (ie is current) then we use current enrollment.
     * Else term is historical, so we display classes for which the user has LMS activity.
     *
     * @api GET /learning/classes{/term}
     * @level 165
     *
     * @param Term $term
     */
    public function getClasses(Term $term)
    {
        $now = \Alaress_Date_Format::now();

        if ($this->getUser()->getHideTimetable() && $term->contains($now)) {

            // Hide timetable is on and trying to look at current term - hide classes

            $hideTimetable = true;
            $folderList = [];
            $classTypes = [];
            $courseSpecs = [];

        } else {

            $folderList = $this->getFolderMapper()->createCollection();
            $classTypes = [];
            $hideTimetable = false;

            // Are we looking at current Classes, or historical ?
            if ($term->contains($now)) {

                /* Convert external subjects to folders */
                foreach ($this->getUser()->getSubjectCollection() as $subject) {
                    try {
                        $folder = $this->getFolderMapper()->findByCode($subject['code']);
                        $folderList->add($folder);
                        $classTypes[$folder->getId()] = $subject['classType'];
                    } catch (MapperException $e) {
                        //not found
                    }
                }
            } else {
                // Is user a teacher or student ?
                if ($this->getUser()->getRole()->isStaff()) {
                    $folderList = $this->getFolderMapper()->findFoldersWithFeedbacksFromTeacher($this->getUser(),
                        $term);
                } elseif ($this->getUser()->getRole()->isStudent()) {
                    $folderList = $this->getFolderMapper()->findFoldersWithResponses($this->getUser(), $term);
                }
            }

            /**
             * Sets of CourseSpecIds, each set indexed by class folder.id
             * @var int[][]
             */
            $courseSpecs = [];

            // Get CourseImpls, then group by Folder
            $courseImpls = $this->courseImplementationMapper->findByFolderId($folderList->getColumnValues('id'));
            $courseImplsPerClass = $courseImpls->groupByCallback(function (CourseImplementation $courseImpl) {
                return $courseImpl->getFolder()->getId();
            });

            // If a course import has unit implementations that are within the specified
            // term, add the course spec to the folder.
            /**
             * @var CourseImplementation[] $courseImpls
             */
            foreach ($courseImplsPerClass as $folderId => $courseImpls) {
                $courseSpecs[$folderId] = [];
                $hasUnitsInTerm = false;
                foreach ($courseImpls as $courseImpl) {
                    foreach ($courseImpl->getUnits() as $unitImpl) {
                        if ($unitImpl->getOpenDate()->compareDateOnly($term->getStart()) >= 0) {
                            if ($term->getActualFinish()->isNull() || $unitImpl->getDueDate()->compareDateOnly($term->getActualFinish()) <= 0) {
                                $hasUnitsInTerm = true;
                                break;
                            }
                        }
                    }
                    if ($hasUnitsInTerm) {
                        $courseSpecId = $courseImpl->getSpecification()->getId();
                        $courseSpecs[$folderId][$courseSpecId] = $courseSpecId;
                    }
                }
            }


            /* Get Timetable colours to use as random colours for null set folders */
            $colourList = $this->getTimetableColourMapper()->findAll();
            $colourIndex = 0;

            foreach ($folderList as $folder) {
                if (!$folder->getColour()) {
                    /* Apply colour from list if no colour exists */
                    $folder->setColour($colourList[$colourIndex]->getHexColour());
                    $colourIndex = ($colourIndex + 1) % $colourList->count();
                }
            }
        }

        $data = [
            'terms' => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'now' => $now,
            'term' => $term,
            'classList' => $folderList,
            'classTypes' => $classTypes,
            'courses' => $courseSpecs,
            'hideTimetable' => $hideTimetable,
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/classes.twig', $data)->setTab('classes'));
    }

    /**
     * Displays a list of grades/results for the specified user and term for all subjects
     *
     * @api   GET /learning/grades/{userId}{/termCode}
     * @level 165
     * @param User $viewee
     * @param Term $term
     */
    public function getGrades(User $viewee, Term $term)
    {
        // Storage for each folders markbook
        $markbooks = [];
        $markbooksFolders = [];

        // Keep a collection of all folders student has an assessment in for use in the dropdown

        $assessments = $this->getAssessmentMapperProxy()->findAllByStudent($viewee, $term);

        /** @var IAssessable $assessment */
        foreach ($assessments as $assessment) {
            $folder = $assessment->getContext()->getFolder();

            if ($folder instanceof Folder) {
                if (!isset($foldersFound[$folder->getId()])) {

                    if (!isset($markbooksFolders[$folder->getId()])) {
                        $markbooksFolders[$folder->getId()] = new Collection();
                    }

                    $markbooksFolders[$folder->getId()]->add($assessment);
                }
            }
        }

        $folders = $this->folderMapper->findFoldersWithFeedbackToStudent($viewee, $term);
        $folders = $folders->mergeUnique($this->folderMapper->findFoldersWithResponses($viewee, $term), 'id');

        // Squish this mess out

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/grades.twig', [
            'student'       => $viewee,
            'term'          => $term,
            'terms'         => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'       => $folders,
            'markbooks'     => $markbooks,
            'mailHandle'    => $this->getSession()->get('mailhandle'),
        ])->setTab('grade'));
    }

    /**
     * Displays a list of grades for the specified user, term and subject
     *
     * @api   GET /learning/grades/{userId}/{termCode}/{subjectId}
     * @level 165
     * @param User $viewee
     * @param Term $term
     * @param Folder $subject
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

        // Let markbook calculate the statistics

        $markbook = new Markbook($assessments, $this->getDefaultGradeGroup(), $this->getUser());
        $statistics = $markbook->getAssessmentStatistics();

        // Get the data for the graph

        $graphInfo = [];
        $graphAssessments = [];
        $graphData = [];
        $graphScatter = [];
        $graphFeedback = [];
        $quizzes = [];

        /** @var IAssessable $assessment */
        foreach ($assessments as $assessment) {

            // If this is a quiz we need to find out if the student has made any attempts so we can display a link to view them

            if ($assessment instanceof AssessmentQuiz)
            {
                try {
                    $quiz = $this->quizMapper->findOneByColumn('assessment_id', $assessment->getId());
                    $attempts = $this->quizAttemptMapper->findByColumnList([
                        'assess_id' =>$quiz->getId(),
                        'user_id' => $viewee->getId()
                    ]);

                    if ($attempts->count() > 0) {
                        $quizzes[$assessment->getId()] = $quiz;
                    }
                } catch (MapperException $e) {
                }
            }

            $graphAssessments[] = $assessment->getTitle();

            if ($assessment->isParticipant($viewee)) {
                $feedback = $assessment->getParticipant($viewee)->findMostRecentVisibleFeedback($this->getUser());
            } else {
                $feedback = null;
            }

            if ($feedback) {
                $graphScatter[] = (float)$feedback->getNormalisedMark();

                if (!empty($feedback->getHumanReadableMark()) || !empty($feedback->getComment())) {
                    $graphInfo[] = [
                        'displayMark' => $feedback->getHumanReadableMark(),
                        'gradientMark' => (int)($feedback->getNormalisedMark() / 10),
                        'info' => $feedback->getComment() == null ? '' : $feedback->getComment()
                    ];
                } else {
                    $graphInfo[] = [];
                }
            } else {
                $graphScatter[] = '';
                $graphInfo[] = [];
            }

            if ($statistics[$assessment->getId()]['count'] > 0) {
                $graphData[] = [
                    // Minimum
                    [
                        'info' => null,
                        'uid' => null,
                        'value' => $statistics[$assessment->getId()]['minNormalized']
                    ],
                    // 1st Quartile
                    [
                        'info' => null,
                        'uid' => null,
                        // TODO: Find the real way to calculate the 1st/3rd quartiles
                        'value' => $statistics[$assessment->getId()]['meanNormalized'] - $statistics[$assessment->getId()]['sdNormalized'],
                    ],
                    // Median
                    [
                        'info' => null,
                        'uid' => null,
                        'value' => $statistics[$assessment->getId()]['meanNormalized']
                    ],
                    // 3rd Quartile
                    [
                        'info' => null,
                        'uid' => null,
                        // TODO: Find the real way to calculate the 1st/3rd quartiles
                        'value' => $statistics[$assessment->getId()]['meanNormalized'] + $statistics[$assessment->getId()]['sdNormalized'],
                    ],
                    // Maximum
                    [
                        'info' => null,
                        'uid' => null,
                        'value' => $statistics[$assessment->getId()]['maxNormalized']
                    ],
                ];
            } else {
                $graphData[] = [];
            }
        }

        // Keep a collection of all folders student has an assessment in for use in the dropdown

        $folders = $this->folderMapper->findFoldersWithFeedbackToStudent($viewee, $term);
        $folders = $folders->mergeUnique($this->folderMapper->findFoldersWithResponses($viewee, $term), 'id');


        // Grab the students conversation history

        $conversation = new Conversation($viewee, $subject);

        // Squeeze it on out

        $returnUrlSuffix = '?'.Redirect::RETURN_URL.'=' . Redirect::encode($_SERVER["REQUEST_URI"]);

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/gradesSubject.twig', [
            'student'            => $viewee,
            'term'               => $term,
            'terms'              => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'            => $folders,
            'subject'            => $subject,
            'markbook'           => $markbook,
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
            'participant'        => $assessment->getParticipant($viewee)
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
}
