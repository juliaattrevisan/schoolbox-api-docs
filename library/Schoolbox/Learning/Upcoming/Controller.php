<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Upcoming;

use Alaress_Date_Format;
use Alaress\Web\Controller\Base;
use External_ClassInterface;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueDateSortStrategy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Markbook\Markbook;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Controller for the upcoming work screen.
 */
class Controller extends Base
{

    /**
     * Limit of upcoming assessments shown in list
     */
    const UPCOMING_WORK_LIMIT = 20;

    /**
     * External Class data source
     *
     * @var External_ClassInterface
     */
    protected $externalClass;

    /**
     * Folder data source
     *
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * User data source
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Term data source
     *
     * @var TermMapper
     */
    protected $termMapper;

    /**
     * Assessment data source
     *
     * @var AssessmentMapperProxy
     */
    protected $assessmentDataSource;

    /**
     * Participant data source
     *
     * @var ParticipantDataSource
     */
    protected $participantDataSource;

    /**
     * @var GradeGroup\Model
     */
    protected $defaultGradeGroup;

    /**
     * Constructor
     *
     * @param External_ClassInterface $externalClass
     * @param FolderMapper            $folderMapper
     * @param UserMapper              $userMapper
     * @param TermMapper              $termMapper
     * @param AssessmentMapperProxy   $assessmentDataSource
     * @param ParticipantDataSource   $participantDataSource
     * @param GradeGroup\Model        $defaultGradeGroup
     */
    public function __construct(
        External_ClassInterface $externalClass,
        FolderMapper $folderMapper,
        UserMapper $userMapper,
        TermMapper $termMapper,
        AssessmentMapperProxy $assessmentDataSource,
        ParticipantDataSource $participantDataSource,
        GradeGroup\Model $defaultGradeGroup
    ) {
        $this->externalClass         = $externalClass;
        $this->folderMapper          = $folderMapper;
        $this->userMapper            = $userMapper;
        $this->termMapper            = $termMapper;
        $this->assessmentDataSource  = $assessmentDataSource;
        $this->participantDataSource = $participantDataSource;
        $this->defaultGradeGroup     = $defaultGradeGroup;
    }

    /**
     * Redirects to the upcoming work screen for the current user.
     *
     * @api GET /learning/due/
     */
    public function getUpcoming()
    {
        $this->getResponse()->redirect->to(
            $this->getRouter()->generate(
                'learningUpcoming.getUpcomingForStudent',
                ['studentId' => $this->getUser()->getId(),]
            )
        );
    }


    /**
     * Displays upcoming work for the given user.
     *
     * @api   GET /learning/due/:studentId{/:date}
     * @param User                $student
     * @param Alaress_Date_Format $date The date to show on the work calendar.
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    public function getUpcomingForStudent(User $student, Alaress_Date_Format $date): void
    {
        $term = $this->termMapper->findForDate($date);

        // Student's subjects and teachers
        $subjects = [];

        // Student's work
        $allWork = $this->assessmentDataSource
            ->findAllByStudent($student, true, $term)
            ->sortByCallback(new DueDateSortStrategy($student));

        // Only get subjects from external if student's timetable isn't hidden.
        // I don't get this logic as class list != timetable, but apparently
        // it's important. ¯\_(ツ)_/¯
        foreach ($this->folderMapper->findForUser($student, !$student->getHideTimetable(), UserAccess::ACCESS_READ, false, true) as $folder) {
            // Load teachers for this subject (if this subject has them)
            $teachers = [];
            if (count($folder->getCodes()) > 0) {
                // Actual teachers only
                $teacherCodes = $this->externalClass->getTeacherName($folder->getCodeString());
                if(is_array($teacherCodes) && count($teacherCodes) > 1) {
                    $teachers = $this->userMapper->findByColumn('synergy_id', $teacherCodes)
                                                 ->filter(
                                                     function (User $teacher) {
                                                         return $teacher->getRole()->isStaff();
                                                     }
                                                 );
                }
            }

            // Student's grades
            // I can just get the assessments out of $allWork
            $assessments = $allWork->filter(function (IAssessable $assessable) use ($folder) {
                return $assessable->getBaseContext()->getFolder()->getId() == $folder->getId();
            });

            // Markbook
            $markbook = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());

            if (!isset($subjects[$folder->getId()])) {
                $subjects[$folder->getId()] = [
                    'teachers' => $teachers,
                    'folder'   => $folder,
                    'grade'    => $this->getConfigLoader()->get('show_overall_grade') ? $markbook->getStudentStatistics()[$student->getId()]: '',
                    'hasPerms'    => Access::getInstance()->getFolderAccess($this->getUser(), $folder->getId()) > UserAccess::ACCESS_DENY,
                ];
            }
        }

        $visibility = new Visibility(Access::getInstance());

        // Only show work that student has permission to view, and can accept their submission
        $upcomingWork = $allWork->filter(function (IAssessable $assessable) use ($student, $visibility) {
            return $visibility->isVisibleToUser($assessable, $student, false);
        });

        // TODO Populate $_SESSION[children] properly
        $children = $this->userMapper->findByColumn('id', array_column($this->getSession()->get('children', []), 'id'));

        $returnUrl = Redirect::queryString($this->getRouter()->generate('learningUpcoming.getUpcomingForStudent', ['id' => $student->getId()]));

        $data = [
            'student'      => $student,
            'date'         => $date,
            'subjects'     => $subjects,
            'mailHandle'   => $this->getSession()->get('mailhandle'),
            'currentTerm'  => $term,
            'children'     => $children,
            'upcomingWork' => $upcomingWork->limit(self::UPCOMING_WORK_LIMIT),
            'returnUrl'    => $returnUrl
        ];

        $namespace = Module::BASENAME;
        $this->getResponse()->content->set(
            $this->buildHtmlOutput("@$namespace/upcoming.twig", $data)
             ->setTitle('Upcoming Work for '.$student->formatName())
             ->setTab('submission') // ¯\_(ツ)_/¯
        );
    }
}
