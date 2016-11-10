<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning;

use Alaress\Web\Controller\Base;
use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\StudentReportCard\Conversation;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\PendingFactory;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Assessable\DataSource as AssessableDataSource;
use Schoolbox\Learning\Assessment\Summator\Average;
use Schoolbox\Learning\Assessment\Summator\Count;
use Schoolbox\Learning\Assessment\Summator\Maximum;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\Assessment\Summator\StandardDeviation;
use Schoolbox\Learning\Assessment\Summator\WeightedAverage;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\User;
use Schoolbox\Assessment\Term\Model as Term;
use Timetable_ColourMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeGroup;

class Controller extends Base
{
    /**
     * @var ShowcaseMapper
     */
    protected $showcaseMapper;

    /**
     * @var TermMapper
     */
    protected $termMapper;

    /**
     * @var GradeGroup
     */
    protected $gradeGroup;

    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var AssessmentMapperProxy
     */
    protected $assessmentMapperProxy;

    /**
     * @var Participant\DataSource
     */
    protected $participantDataSource;

    /**
     * @var AssessableDataSource
     */
    protected $assessableDataSource;

    /**
     * Controller constructor.
     *
     * @param ErrorMapper            $errorMapper
     * @param AssessmentMapperProxy  $assessmentMapperProxy
     * @param Participant\DataSource $participantDataSource
     * @param TermMapper             $termMapper
     * @param FolderMapper           $folderMapper
     * @param GradeGroup             $gradeGroup
     * @param ShowcaseMapper         $showcaseMapper
     * @param AssessableDataSource   $assessableDataSource
     */
    public function __construct(
        ErrorMapper $errorMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        Participant\DataSource $participantDataSource,
        FolderMapper $folderMapper,
        TermMapper $termMapper,
        GradeGroup $gradeGroup,
        ShowcaseMapper $showcaseMapper,
        AssessableDataSource $assessableDataSource
    ) {
        $this->setErrorMapper($errorMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setParticipantDataSource($participantDataSource);
        $this->setTermMapper($termMapper);
        $this->setFolderMapper($folderMapper);
        $this->setGradeGroup($gradeGroup);
        $this->setShowcaseMapper($showcaseMapper);
        $this->setAssessableDataSource($assessableDataSource);
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
            $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getDue', [
                'userId' => $this->getUser()->getId()
            ]));
        }

        // If no staff explicitly requested, redirect to current user
        $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getPendingAssessmentsForUser', [
            'userId' => $this->getUser()->getId()
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
                $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getDue', [
                    'userId' => $viewee->getId()
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

        // TODO - both this, External_Class() and all Mappers should be singletons pulled from the DI.
        // TODO - see pattern applied to MapperFactory in Modules.
        $externalUser = $viewee->getSubjectCollection()
            ? null
            : new \External_User();

        $term = TermMapper::getInstance()->findForToday();

        $factory = new PendingFactory($viewee, $externalUser, new \External_Class(), $term);
        $assessments = $factory->getPendingAssessments();

        /** @var IAssessable[] $assessmentsToMark */
        $assessmentsToMark = new Collection();
        foreach($assessments as $assessment){
            if($assessment->numMarkingRequired()){
                $assessmentsToMark->add($assessment);
            }
        }
        $assessmentsToMark->sortBy('dueAt', 'asc');

        /** @var IAssessable[] $assessmentsToMark */
        $assessmentsToChase = new Collection();
        $tomorrow = (new \Alaress_Date_Format())->addDays(1);
        foreach($assessments as $assessment){
            if($assessment->getDueAt()->lt($tomorrow) && $assessment->numNoResponseOrFeedback()){
                $assessmentsToChase->add($assessment);
            }
        }
        $assessmentsToChase->sortBy('dueAt', 'asc');

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/pendingAssessments.twig', [
            'viewer'             => $this->getUser(),
            'viewee'             => $viewee,
            'assessmentsToMark'  => $assessmentsToMark,
            'assessmentsToChase' => $assessmentsToChase,
        ]));
    }

    /**
     * Displays a list of classes for the current logged in user
     *
     * @api GET /learning/classes
     * @level 165
     *
     */
    public function getClasses() {

        /* Get Timetable colours to use as random colours for null set folders */
        $colourList = Timetable_ColourMapper::getInstance()->findAll();
        $colourIndex = 0;

        $folderList = new Collection();

        /* Convert external subjects to folders */
        foreach($this->getUser()->getSubjectCollection() as $subject ) {
            try {
                $folder = FolderMapper::getInstance()->findByCode($subject['code']);
                if (!$folder->getColour()) {
                    /* Apply colour from list if no colour exists */
                    $folder->setColour($colourList[$colourIndex]->getHexColour());
                    $colourIndex = ($colourIndex + 1) % $colourList->count();
                }
                $folderList->add($folder);
            } catch (\Schoolbox\Mapper\Exception $e) {
                //not found
            }
        }


        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/classes.twig', [
            'currentUser'    => $this->getUser(),
            'currentSemester'=> TermMapper::getInstance()->findForToday(),
            'classList'      => $folderList,

        ]));
    }

    /**
     * Displays a list of marks for students in the specified class
     *
     * @api   GET /learning/markbook/:classId
     * @level 165
     * @param Folder $class
     */
    public function getMarkbook(Folder $class)
    {
        // Replaces /submission/listSubmission/{termSlug}/{folderId}
        //       eg /submission/listSubmission/{termSlug}/{folderId}
        // Replaces /submission/viewSubmission/{dueWorkId}
        //       eg /submission/viewSubmission/56

        // Is current user not SuperUser or Staff ?
        if (!($this->getUser()->isSuperuser() || $this->getUser()->getRole()->isStaff())) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learning.getDue', [
                'userId' => $this->getUser()->getId()
            ]));
        }


        // Does this class exist ?
        if (!$class->getId()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        // Generate data: assessments and their Participants
        $assessments = $this->getAssessmentMapperProxy()->findAllForFolderId($class->getId());
        foreach ($assessments as $assessment) {
            $assessment->setAllParticipants($this->getParticipantDataSource()->findForAssessment($assessment));
        }
        // The markbook will handle statistics
        $markbook = new Markbook($assessments);

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/markbook.twig', [
            'class'    => $class,
            'markbook' => $markbook,
        ]));
    }

    /**
     * Displays a list assessables that a student needs to complete
     *
     * @api   GET /due/{userId}
     * @level 165
     * @param User $viewee
     */
    public function getDue(User $viewee)
    {
        // TODO - generate data
        $assessmentsDue = [
            Assessment\Controller::getDummyAssessment(2),
            Assessment\Controller::getDummyAssessment(3),
            Assessment\Controller::getDummyAssessment(4),
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/dueWork.twig', [
            'viewee'         => $viewee,
            'assessmentsDue' => $assessmentsDue,
        ]));
    }

    /**
     * Displays a list of grades/results for the specified user and term for all subjects
     *
     * @api   GET /learning/grades/{userId}{/termCode}
     * @level 165
     * @param User $student
     * @param Term $term
     */
    public function getGrades(User $student, Term $term)
    {
        // Storage for each folders markbook
        $markbooks = [];
        $markbooksFolders = [];

        // Keep a collection of all folders for use in the dropdown

        $folders = new Collection;

        $assessments = $this->getAssessableDataSource()->findForUser($student, $term);

        /** @var IAssessable $assessment */
        foreach ($assessments as $assessment) {
            $folder = $assessment->getContext()->getFolder();

            if ($folder instanceof Folder) {
                if (!isset($foldersFound[$folder->getId()])) {

                    if (!isset($markbooksFolders[$folder->getId()])) {
                        $markbooksFolders[$folder->getId()] = new Collection();
                        $folders->add($folder);
                    }

                    $markbooksFolders[$folder->getId()]->add($assessment);
                }
            }
        }

        foreach ($markbooksFolders as $folderId => $assessments) {
            $markbooks[$folderId] = (new Markbook($assessments))->getStudentStatistics()[$student->getId()];
        }

        // Squish this mess out

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/grades.twig', [
            'student'       => $student,
            'term'          => $term,
            'terms'         => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'       => $folders,
            'markbooks'     => $markbooks
        ]));
    }

    /**
     * Displays a list of grades for the specified user, term and subject
     *
     * @api   GET /learning/grades/{userId}/{termCode}/{subjectId}
     * @level 165
     * @param User $student
     * @param Term $term
     * @param Folder $subject
     */
    public function getGradesForSubject(User $student, Term $term, Folder $subject)
    {
        $assessments = $this->getAssessmentMapperProxy()->findAllForContext($subject)->sortBy('dueAt', 'asc');

        // Grab all the participants

        /** @var IAssessable $assessment */
        foreach ($assessments as $assessment) {
            $assessment->setAllParticipants($this->getParticipantDataSource()->findForAssessment($assessment));
        }

        // Let markbook calculate the statistics

        $markbook = new Markbook($assessments);
        $statistics = $markbook->getAssessmentStatistics();

        // Get the data for the graph

        $graphInfo = [];
        $graphAssessments = [];
        $graphData = [];
        $graphScatter = [];
        $graphFeedback = [];
        $showcase = [];

        /** @var IAssessable $assessment */
        foreach ($assessments as $assessment) {

            // Showcase if used to determine if the option to publish to the eportfolio should appear

            $showcase[$assessment->getId()] = $this->getShowcaseMapper()->findByColumnList(
                array(
                    'uid'               => $student->getId(),
                    'submission_box_id' => $assessment->getId()
                )
            );

            $graphAssessments[] = $assessment->getTitle();

            $feedback = $assessment->getParticipant($student)->findMostRecentFeedback();

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

        // Grab the students conversation history

        $conversation = new Conversation($student, $subject);

        // Squeeze it on out

        $this->getResponse()->content->set($this->buildHtmlOutput('@learning/gradesSubject.twig', [
            'student'           => $student,
            'term'              => $term,
            'terms'             => $this->getTermMapper()->findAll()->sortBy('start', 'desc'),
            'folders'           => $this->getFolderMapper()->findFoldersWithSubmissions($student, $term),
            'subject'           => $subject,
            'markbook'          => $markbook,
            'graphInfo'         => json_encode($graphInfo),
            'graphAssessments'  => json_encode($graphAssessments),
            'graphData'         => json_encode($graphData),
            'graphScatter'      => json_encode($graphScatter),
            'graphFeedback'     => $graphFeedback,
            'canComment'        => $conversation->canComment($this->getUser()),
            'comments'          => $conversation->getVisibleComments($this->getUser()),
            'showcase'          => $showcase
        ]));
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
     * @return Participant\DataSource
     */
    protected function getParticipantDataSource()
    {
        return $this->participantDataSource;
    }

    /**
     * @param Participant\DataSource $participantDataSource
     * @return static
     */
    protected function setParticipantDataSource(Participant\DataSource $participantDataSource)
    {
        $this->participantDataSource = $participantDataSource;
        return $this;
    }

    /**
     * @return AssessableDataSource
     */
    protected function getAssessableDataSource()
    {
        return $this->assessableDataSource;
    }

    /**
     * @param AssessableDataSource $assessableDataSource
     * @return static
     */
    protected function setAssessableDataSource(AssessableDataSource $assessableDataSource)
    {
        $this->assessableDataSource = $assessableDataSource;
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
    protected function getGradeGroup()
    {
        return $this->gradeGroup;
    }

    /**
     * @param GradeGroup $gradeGroup
     * @return static
     */
    protected function setGradeGroup(GradeGroup $gradeGroup)
    {
        $this->gradeGroup = $gradeGroup;
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
     * @param ShowcaseMapper $showcaseMapper
     * @return static
     */
    protected function setShowcaseMapper(ShowcaseMapper $showcaseMapper)
    {
        $this->showcaseMapper = $showcaseMapper;
        return $this;
    }
}
