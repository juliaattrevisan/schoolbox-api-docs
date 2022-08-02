<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Ms\Pastoral\StudentInvolvement\Report;

use Alaress\Statistics\Statistics;
use Custom\Ms\Pastoral\StudentInvolvement\Gateway;
use RangeException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

use function count;

/**
 * Factory for building various eReport data sets.
 */
class Factory
{
    // This is pretty fragile. Must match value configured in Admin WorkType lists
    private const WORK_TYPE_ASSESSMENT_TASK = 1;

    /**
     * Regular expression for lateral learning class pages: used to identify if
     * a subject page should instead display as a lateral learning report.
     */
    private const LATERAL_LEARNING_SUBJECT_NAME_REGEX = '/Lateral\\s+Learning/i';

    /**
     * The year levels for the Middle School variant of the report.
     */
    private const REPORT_YEARS_MIDDLE_SCHOOL = [7, 8, 9];

    /**
     * The year levels for the Senior School variant of the report.
     */
    private const REPORT_YEARS_SENIOR_SCHOOL = [10, 11, 12];


    /**
     * @var Gateway
     */
    private $gateway;

    /**
     * @var UserMapper
     */
    private $userMapper;

    /**
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * @var AssessmentDataSource
     */
    private $assessmentDataSource;

    /**
     * @var RubricDataSource
     */
    private $rubricMapper;

    /**
     * The currently authenticated user.
     *
     * @var User
     */
    private $currentUser;

    /**
     * Data source for fetching users who have access to a folder.
     *
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * Constructor
     *
     * @param Gateway              $gateway
     * @param UserMapper           $userMapper
     * @param FolderMapper         $folderMapper
     * @param AssessmentDataSource $assessmentDataSource
     * @param RubricDataSource     $rubricMapper
     * @param User                 $currentUser
     * @param UserAccessMapper     $userAccessMapper
     */
    public function __construct(
        Gateway $gateway,
        UserMapper $userMapper,
        FolderMapper $folderMapper,
        AssessmentDataSource $assessmentDataSource,
        RubricDataSource $rubricMapper,
        User $currentUser,
        UserAccessMapper $userAccessMapper
    ) {
        $this->gateway              = $gateway;
        $this->userMapper           = $userMapper;
        $this->folderMapper         = $folderMapper;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->rubricMapper         = $rubricMapper;
        $this->currentUser          = $currentUser;
        $this->userAccessMapper     = $userAccessMapper;
    }

    /**
     * Builds a TutorGroup object for a given year and student.
     *
     * This is used as a data store for the year level eReport.
     *
     * @param int            $year
     * @param User           $student
     * @param Subject\Base[] $subjectList      `$student`'s subjects in `$year`
     * @param string|null    $roleTypeOverride
     *
     * @return TutorGroup\Base
     */
    public function buildTutorGroup($year, User $student, array $subjectList, ?string $roleTypeOverride)
    {
        $tutorGroup = self::buildTutorGroupReport($year, $student);
        $tutorGroup
            ->setName($year . ' eReport')
            ->setStudent($student)
            ->setYear($year)
            ->setActivities($this->gateway->getAllActivities($year, $student))
            ->setAttendances($this->gateway->getAllAttendances($year, $student))
            ->setTutors($this->gateway->getAllTutors($year, $student))
            ->setTutorGroup($this->gateway->getYearLevelGroup($year, $student))
            // Add teachers into subjects - debatable if this is part of tutor group?
            ->setSubjects(
                array_map(
                    [$this, 'augmentSubjectWithTeacher'],
                    $this->gateway->getAllSubjectsForYear($year, $student)
                )
            );

        // Add grades for the tutor group class page
        $tutorGroupCode   = $tutorGroup->getTutorGroup();
        $publishedOnly    = !$this->doesDraftFeedbackContribute($year);
        $folderWithGrades = $this->getFolderWithGrades($tutorGroupCode['code'], $student, $publishedOnly, $roleTypeOverride);
        $tutorGroup
            ->setSubjectCode($tutorGroupCode['code'])
            ->setFolder($folderWithGrades['folder'])
            ->setGrades($folderWithGrades['grades'])
            ->moveToNamedGrade('athletics', 'Athletics Carnival')
            ->moveToNamedGrade('swimming', 'Swimming Carnival')
            ->moveToNamedGrade('camp', 'School Camp')
            ->moveToNamedGrade('uniform', 'Uniform')
            ->moveToNamedGrade('pastoral', 'Pastoral activities involvement')
            ->moveToNamedGrade('tutorComment', 'Tutor Comment');

        // Add write level staff as coordinators
        $folder = $tutorGroup->getFolder();
        if ($folder instanceof Folder && $folder->getId()) {
            $tutorGroup->setCoordinators(
                $this->userAccessMapper->findUsersInCategory(
                    $folder,
                    Role::STAFF,
                    UserAccess::ACCESS_WRITE,
                    false
                )
            );
        }

        // Senior report only: collate endeavor grades
        if ($tutorGroup instanceof TutorGroup\SeniorSchool) {
            $allFolders = [];
            $allGrades  = [];

            // If the student was in the TASS class, but not in any assessment,
            // `getFolderWithGrades` won't find them.
            // In this case, add a fake folder to the list so it still shows.
            $fakeFolderId = -1;
            foreach ($subjectList as $subject) {
                // First determine if the subject should even show on the senior
                // report: don't load it if it shouldn't.
                $subjectName = $subject->getName();
                if (!$tutorGroup->isSubjectDisplayed($subjectName)) {
                    continue;
                }

                // The senior school report cares about the year level that
                // a folder's grades are due in.
                // This is due to Meriden's class folder rollover process:
                // * their Year 11 classes are rolled over and archived
                // * these folders are then un-archived, and the year 12 class
                //   code is added to these folders
                // This is to preserve continuity between the Year 11 and 12
                // class material: this is particularly necessary as Year 12
                // starts for students in term 4 of Year 11.
                // This ensures that endeavor grades given for year 11 don't
                // show in the year 12 report.
                $folderWithGrades = $this->getFolderWithGrades(
                    $subject->getSubjectCode(),
                    $student,
                    $publishedOnly,
                    $roleTypeOverride,
                    $year
                );

                // Ensure folder actually exists
                $folder = $folderWithGrades['folder'] ?? null;
                if ($folder instanceof Folder && $folder->getId()) {
                    // Set the subject's name into the folder,
                    // so that it doesn't have any rolled-over codes.
                    // (Clone so this doesn't inadvertently affect any caches)
                    $allFolders[] = (clone $folder)->setName($subjectName);
                    $allGrades[]  = $folderWithGrades['grades'];
                } else {
                    $allFolders[] = (new Folder())
                        ->setId($fakeFolderId--)
                        ->setName($subjectName);
                    $allGrades[]  = [];
                }
            }

            $tutorGroup->injectEndeavorGrades($allFolders, array_merge(...$allGrades), $publishedOnly);
        }

        return $tutorGroup;
    }

    /**
     * Builds a Subject object for a given year, student and subject.
     *
     * This is used as a data store for the subject eReport.
     *
     * @param int            $year
     * @param User           $student
     * @param string         $subjectCode
     * @param Subject\Base[] $subjectList     `$student`'s subjects in `$year`
     * @param string|null    $roleTypeOverride
     *
     * @return Subject\Base
     */
    public function buildSubject(
        int $year,
        User $student,
        string $subjectCode,
        array $subjectList,
        ?string $roleTypeOverride
    ): Subject\Base {
        $folderWithGrades = $this->getFolderWithGrades(
            $subjectCode,
            $student,
            !$this->doesDraftFeedbackContribute($year),
            $roleTypeOverride
        );
        $folder = $folderWithGrades['folder'];

        // Treat the subject as a lateral learning subject if either of these
        // match the lateral learning subject regular expression:
        // * the folder name
        // * the subject name
        // (Need to match both as the student may not be a participant of the
        //  lateral learning subject anymore.)
        $subjectData = $this->gateway->getSubject($year, $subjectCode, true);
        if (
            ($folder && self::isSubjectLateralLearning($folder->getName()))
            || self::isSubjectLateralLearning($subjectData['sub_desc'] ?? '')
        ) {
            return $this->buildLateralLearning(
                $subjectData,
                $folder,
                $year,
                $student,
                $subjectCode,
                $subjectList,
                $roleTypeOverride,
            );
        }
        return $this->buildStandard(
            $this->gateway->getSubject($year, $subjectCode, false),
            $folder,
            $folderWithGrades['grades'],
            $year,
            $student,
            $subjectCode,
        );
    }

    /**
     * Builds a report object for the subject report for a given year, student
     * and subject code.
     *
     * This is used as a data store for the eReport's standard subject page.
     *
     * @param array       $externalData
     * @param Folder|null $folder
     * @param array       $grades
     * @param int         $year
     * @param User        $student
     * @param string      $subjectCode
     * @return Subject\Standard
     */
    private function buildStandard(
        array $externalData,
        ?Folder $folder,
        array $grades,
        int $year,
        User $student,
        string $subjectCode
    ): Subject\Standard {
        $subject      = new Subject\Standard();
        $externalData = $this->augmentSubjectWithTeacher($externalData);
        $subject
            ->setName($externalData['sub_desc'])
            ->setDescription($externalData['outline_text'])
            ->setTeachers($externalData['teachers'])
            ->setStudent($student)
            ->setYear($year)
            ->setSubjectCode($subjectCode);

        // Add grades for the class page
        $publishedOnly = !$this->doesDraftFeedbackContribute($year);
        $subject
            ->setFolder($folder)
            ->setCohortFolders($folder ? $this->getCohortFolders($folder, $subjectCode) : [])
            ->setGrades($grades)
            ->moveToNamedGrade('courseGrade', 'Course Grade')
            ->moveToNamedGrade('endeavourGrades', 'Endeavour Grades')
            ->moveToNamedGrade('endeavourGrade1', 'Endeavour Grade Term 1')
            ->moveToNamedGrade('endeavourGrade2', 'Endeavour Grade Term 2')
            ->moveToNamedGrade('endeavourGrade3', 'Endeavour Grade Term 3')
            ->moveToNamedGrade('endeavourGrade4', 'Endeavour Grade Term 4')
            ->moveToNamedGrade('learningApproaches', 'Approaches to Learning');

        // Add grade distributions, only for assessments which have them
        $newGrades = [];
        foreach ($subject->getGrades() as $key => $grade) {
            $distribution = $this->buildDistribution($grade, $subject->getCohortFolders(), $publishedOnly);
            if (!empty($distribution)) {
                $grade['distribution'] = $distribution;
            }
            $newGrades[$key] = $grade;
        }
        $subject->setGrades($newGrades);

        // Add course grade distribution
        $courseGrade = $subject->getNamedGrade('courseGrade');
        if (!empty($courseGrade)) {
            $courseGrade['distribution'] = $this->buildDistribution(
                $courseGrade,
                $subject->getCohortFolders(),
                $publishedOnly
            );
            $subject->setNamedGrade('courseGrade', $courseGrade);
        }

        return $subject;
    }

    /**
     * Builds a report object for the lateral learning report for a given year
     * and student.
     *
     * This is used as a data store for the lateral learning eReport.
     *
     * @param array          $externalData
     * @param Folder|null    $folder
     * @param int            $year
     * @param User           $student
     * @param string         $subjectCode
     * @param Subject\Base[] $subjectList `$student`'s subjects in `$year`
     * @param string|null    $roleTypeOverride
     * @return Subject\LateralLearning
     */
    private function buildLateralLearning(
        array $externalData,
        ?Folder $folder,
        int $year,
        User $student,
        string $subjectCode,
        array $subjectList,
        ?string $roleTypeOverride
    ): Subject\LateralLearning {
        // Need a course description
        $lateralLearning = new Subject\LateralLearning();
        $lateralLearning
            ->setName($externalData['sub_desc'])
            ->setDescription($externalData['outline_text'])
            ->setStudent($student)
            ->setYear($year)
            ->setSubjectCode($subjectCode)
            ->setFolder($folder);


        // Put together all the stuff for the lateral learning report
        $publishedOnly = !$this->doesDraftFeedbackContribute($year);
        return $lateralLearning->injectIndicatorMarks(
            $this->collateAllGrades($subjectList, $student, $publishedOnly, $roleTypeOverride)
        );
    }

    /**
     * Which year level was `$student` in during calendar year `$year`?
     *
     * * All reports are only valid for students who started year 7 in 2015+
     * * Senior year (10-12) reports are only valid for years 2021 and future
     *
     * In addition, subject pages are only valid for students in years 7-9.
     *
     * @param int         $year        The calendar year for which the report is
     *                                 being built
     * @param User        $student     The student for whom it's being built
     * @param string|null $subjectCode If provided, the subject code for which
     *                                 it is being built
     * @return int
     * @throws RangeException if `$student` is not in a recognised year for this
     *                        report.
     */
    public static function determineYearLevel(int $year, User $student, ?string $subjectCode): int
    {
        $thisYear  = (int)date('Y');
        $startYear = $thisYear + 7 - (int)$student->getYear()->getName();
        if ($startYear < 2015) {
            throw new RangeException("Reports are only for students who started year 7 in 2015 or later");
        }

        // Determine the year level of the report
        $reportYearLevel = ((int)$student->getYear()->getName()) - ($thisYear - $year);
        if (self::isReportYearSeniorSchool($reportYearLevel)) {
            if (isset($subjectCode)) {
                throw new RangeException("Report subject pages are only for years 7-9");
            }
            if ($year < 2021) {
                throw new RangeException("Senior reports are not available prior to 2021");
            }
        } elseif (!self::isReportYearMiddleSchool($reportYearLevel)) {
            throw new RangeException("Reports are only for years 7-12");
        }
        return $reportYearLevel;
    }

    /**
     * Is this report year valid for a middle school report?
     *
     * @param int $reportYearLevel
     * @return bool
     */
    private static function isReportYearMiddleSchool(int $reportYearLevel)
    {
        return in_array($reportYearLevel, self::REPORT_YEARS_MIDDLE_SCHOOL);
    }

    /**
     * Is this report year valid for a senior school report?
     *
     * @param int $reportYearLevel
     * @return bool
     */
    private static function isReportYearSeniorSchool(int $reportYearLevel)
    {
        return in_array($reportYearLevel, self::REPORT_YEARS_SENIOR_SCHOOL);
    }

    /**
     * Builds a list of all of the tutor groups that this student has been in.
     *
     * They will be populated with only a year of study and student.
     *
     * @param User $student
     *
     * @return array|TutorGroup\Base[]
     */
    public function buildTutorGroupList(User $student)
    {
        $tutorGroups = [];
        foreach ($this->gateway->getAllYearLevelGroups($student) as $level) {
            // Which variant gets built?
            try {
                $tutorGroup = self::buildTutorGroupReport((int)$level['yr_study'], $student);
            } catch (RangeException $e) {
                continue;
            }
            $tutorGroup
                ->setName($level['yr_study'] . ' eReport')
                ->setStudent($student)
                ->setYear($level['yr_study'])
                ->setSubjectCode($level['code']);
            $tutorGroups[$level['yr_study']] = $tutorGroup;
        }
        return $tutorGroups;
    }

    /**
     * Builds a list of all of the student's subjects for this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array|Subject\Base[]
     */
    public function buildSubjectList($year, User $student)
    {
        $subjects = [];
        $result   = array_map(
            [$this, 'augmentSubjectWithTeacher'],
            $this->gateway->getAllSubjectsForYear($year, $student)
        );

        $lateralLearning = null;
        foreach ($result as $row) {
            if (self::isSubjectLateralLearning($row['sub_desc'])) {
                $lateralLearning = new Subject\LateralLearning();
                $lateralLearning
                    ->setName($row['sub_desc'])
                    ->setStudent($student)
                    ->setYear($row['yr_study'])
                    ->setSubjectCode($row['code']);
            } else {
                $subject = new Subject\Standard();
                $subject
                    ->setName($row['sub_desc'])
                    ->setStudent($student)
                    ->setYear($row['yr_study'])
                    ->setSubjectCode($row['code'])
                    ->setTeachers($row['teachers']);
                $subjects[$row['code']] = $subject;
            }
        }

        // Ensure lateral learning is at the head of the list, if it's there
        $subjects = array_values($subjects);
        if ($lateralLearning) {
            array_unshift($subjects, $lateralLearning);
        }
        return $subjects;
    }

    /**
     * Gets the (current or archived) folder for this subject code and student,
     * or an empty one if not found.
     *
     * @param string      $subjectCode
     * @param User        $student
     * @param bool        $publishedOnly
     * @param string|null $roleTypeOverride
     * @param int|null    $calendarYear     If provided: only fetch grades for
     *                                      assessments due in this calendar
     *                                      year.
     *
     * @return array{folder: Folder|null, grades: array}
     */
    private function getFolderWithGrades(
        $subjectCode,
        User $student,
        bool $publishedOnly,
        ?string $roleTypeOverride,
        ?int $calendarYear = null
    ) {
        // Find by category code, and figure out if they're involved.
        $folder = $this->folderMapper->findForCampusAndClass('', $subjectCode);
        if ($folder) {
            $involvement = $this->getStudentInvolvement($folder, $student, $publishedOnly, $roleTypeOverride, $calendarYear);
            if (!empty($involvement['grades'])) {
                return [
                    'folder' => $folder,
                    'grades' => $involvement['grades'],
                ];
            }
        }

        // Find by archived code, as this folder might have been rolled over.
        // Multiple rolled-over categories with this code could exist,
        // get the one that has the requested student in it.
        // To do this, every matching category has to be checked.
        $archivedFolders = $this->folderMapper->findAllByArchivedCode($subjectCode);
        foreach ($archivedFolders as $archivedFolder) {
            $involvement = $this->getStudentInvolvement($archivedFolder, $student, $publishedOnly, $roleTypeOverride, $calendarYear);
            if (!empty($involvement['grades'])) {
                return [
                    'folder' => $archivedFolder,
                    'grades' => $involvement['grades'],
                ];
            }
        }

        // Is a year level provided, and nothing found?
        // Try finding next year's subject code: if one was found, find the
        // involvement for this calendar year.
        // This accounts for Meriden's class folder rollover process for year
        // 11 subjects:
        // * their Year 11 classes are rolled over and archived
        // * these folders are then un-archived, and the year 12 class
        //   code is added to these folders
        // This means that Year 11 endeavor grades are associated with the
        // student's current Year 12 class code: this change fetches only the
        // Year 11 grades from that class code's folder.
        if (isset($calendarYear)) {
            $nextYearSubjectCode = $this->gateway->getNextYearSubjectCode($calendarYear, $subjectCode);
            if ($nextYearSubjectCode) {
                $folder = $this->folderMapper->findForCampusAndClass('', $nextYearSubjectCode);
                if ($folder) {
                    $involvement = $this->getStudentInvolvement(
                        $folder,
                        $student,
                        $publishedOnly,
                        $roleTypeOverride,
                        $calendarYear
                    );
                    if (!empty($involvement['grades'])) {
                        return [
                            'folder' => $folder,
                            'grades' => $involvement['grades'],
                        ];
                    }
                }

                // No current folder for next year's code?
                // find an archived folder
                $archivedFolders = $this->folderMapper->findAllByArchivedCode($nextYearSubjectCode);
                foreach ($archivedFolders as $archivedFolder) {
                    $involvement = $this->getStudentInvolvement($archivedFolder, $student, $publishedOnly, $roleTypeOverride, $calendarYear);
                    if (!empty($involvement['grades'])) {
                        return [
                            'folder' => $archivedFolder,
                            'grades' => $involvement['grades'],
                        ];
                    }
                }
            }
        }

        // everything failed? return a null folder
        return [
            'folder' => null,
            'grades' => [],
        ];
    }

    /**
     * Is $student involved in $folder?
     *
     * This performs the following checks:
     * * Is this student currently a member of the class?
     * * Does this student have a grade in this folder?
     *
     * @param Folder      $folder
     * @param User        $student
     * @param bool        $publishedOnly
     * @param string|null $roleTypeOverride
     * @param int|null    $calendarYear     If provided: only fetch grades for
     *                                      assessments due in this calendar
     *                                      year.
     *
     * @return array{grades: array}
     */
    private function getStudentInvolvement(
        Folder $folder,
        User $student,
        bool $publishedOnly,
        ?string $roleTypeOverride,
        ?int $calendarYear = null
    ) {
        // Is $student a member of $folder (by class code?)
        if ($folder->getCodes()) {
            foreach ($student->getSubjectCollection() as $subject) {
                foreach ($folder->getCodes() as $code) {
                    if ($code->getCode() === $subject['code']) {
                        // Student is involved, even if they have no grades.
                        // Try to get them anyway.
                        return [
                            'grades' => $this->getGrades($folder, $student, $publishedOnly, $roleTypeOverride, $calendarYear),
                        ];
                    }
                }
            }
        }

        // If not, has $student got a grade in this folder?
        $grades = $this->getGrades($folder, $student, $publishedOnly, $roleTypeOverride, $calendarYear);
        if (!empty($grades)) {
            return [
                'grades' => $grades,
            ];
        }

        // Not involved in this folder at all? Return nothing
        return ['grades' => [],];
    }

    /**
     * Gets all assessment and quiz grades.
     *
     * @param Folder      $folder
     * @param User        $student
     * @param bool        $publishedOnly
     * @param string|null $roleTypeOverride
     * @param int|null    $calendarYear     If provided: only fetch grades for
     *                                      assessments due in this calendar
     *                                      year.
     *
     * @return array<string, array{assessment: IAssessable, rubric: MarkedGrid|null, pretty_mark: string, mark: float, comment: string}>
     */
    private function getGrades(
        Folder $folder,
        User $student,
        bool $publishedOnly,
        ?string $roleTypeOverride,
        ?int $calendarYear = null
    ) {
        $allGrades = [];

        // Only due works are shown
        // Not interested in work types other than assessment tasks
        foreach (
            $this->assessmentDataSource->findAllByFolderIdForReview(
                $folder->getId(),
                $student,
                ['order' => ['due_date' => 'asc'],]
            ) as $assessment
        ) {
            if (isset($calendarYear)) {
                $assessmentYear = (int)$assessment->getDueAt()->getYear();
                if ($assessmentYear !== $calendarYear) {
                    continue;
                }
            }

            $participant = $assessment->getParticipantIfExtant($student);
            if ($participant) {
                // Filter this grade out if not visible to the current user.
                if (!$assessment->isFeedbackVisibleToUser($this->currentUser)) {
                    continue;
                }

                // Filter this grade out if there is a role type override,
                // and feedback isn't visible to it
                if ($roleTypeOverride && !$assessment->isFeedbackVisibleToRoleType($roleTypeOverride)) {
                    continue;
                }

                $feedback = $participant->getAuthoritativeFeedback($publishedOnly);
                $rubric   = null;
                if (
                    $assessment instanceof DueWork
                    && $feedback instanceof DueWorkFeedback
                    && $assessment->getUseRubric()
                ) {
                    $rubric = $this->rubricMapper->retrieveMarkedGrid($feedback);
                    // Install the marked grid into the feedback, so that it is
                    // accessible by the lateral learning methods.
                    $feedback->setRubricMarkedGrid($rubric);
                }
                if ($feedback) {
                    $allGrades['a' . $assessment->getId()] = [
                        'assessment'  => $assessment,
                        'rubric'      => $rubric,
                        'pretty_mark' => $feedback->getHumanReadableMark(),
                        'mark'        => $feedback->getNormalisedMark(),
                        'comment'     => $feedback->getComment(),
                    ];
                }
            }
        }
        return $allGrades;
    }

    /**
     * Gets the cohort folders for this folder.
     *
     * @param Folder $category
     * @param string $subjectCode The original subject code (pre-rollover)
     *                            for $category.
     *
     * @return Folder[]
     */
    private function getCohortFolders(Folder $category, $subjectCode)
    {
        // Find the equivalent assessments for each cohort class
        // (class with the same second part of the class code)
        $cohortList = [];
        $parent     = $category->getParent();
        $parentId   = $parent instanceof Folder ? $parent->getId() : null;
        foreach ($this->gateway->getOtherSubjectClasses($subjectCode) as $otherCode) {
            // All archived or live codes with the same parent as $category
            // are cohorts.
            try {
                $liveCategory = $this->folderMapper->findByCode($otherCode);
                if ($liveCategory->getParent()->getId() == $parentId) {
                    $cohortList[] = $liveCategory;
                }
            } catch (MapperException $e) {
                // noop
            }
            foreach ($this->folderMapper->findAllByArchivedCode($otherCode) as $archivedCategory) {
                if ($archivedCategory->getParent()->getId() == $parentId) {
                    $cohortList[] = $archivedCategory;
                }
            }
        }
        return $cohortList;
    }

    /**
     * Augments a subject retrieved by getAllSubjectsForYear() with one or
     * more teachers.
     *
     * @param array $subject
     *
     * @return array {
     *  teachers: User[]
     * }
     */
    private function augmentSubjectWithTeacher(array $subject)
    {
        // Get every teacher by their tch_code
        if (!empty($subject['tch_code'])) {
            $subject['teachers'] = $this->userMapper->findByColumn('synergy_id', $subject['tch_code']);
        } else {
            $subject['teachers'] = [];
        }
        return $subject;
    }

    /**
     * Builds a distribution of grades for this grade.
     *
     * @param array          $grade
     * @param array|Folder[] $cohortFolders
     * @param bool           $publishedOnly
     *
     * @return array {
     *   grouped: array(string, array)
     *   continuous: array {
     *     marks: float[]
     *     mean:  float
     *     sd:    float
     *   }
     * }
     */
    private function buildDistribution($grade, $cohortFolders, bool $publishedOnly)
    {
        // Only interested in distribution of due works that are assessment tasks
        $baseAssessment = $grade['assessment'];
        if (!$baseAssessment instanceof DueWork) {
            return [];
        }
        $workType = $baseAssessment->getWorkType();
        if (!($workType instanceof WorkType && (int)$workType->getId() === self::WORK_TYPE_ASSESSMENT_TASK)) {
            return [];
        }

        // Get cohort for this class
        // Original implementation searched on assessment name,
        // but we can use unit spec ID here.
        $assessments    = $this->assessmentDataSource->createCollection();
        $specAssessment = $baseAssessment->getSpecificationAssessment();
        if ($specAssessment) {
            $cohortFolderIds = [];
            foreach ($cohortFolders as $cohortFolder) {
                $cohortFolderIds[] = $cohortFolder->getId();
            }
            $assessments = $this->assessmentDataSource->findAllByFolderIdForReview(
                $cohortFolderIds,
                null,
                [
                    'spec_assessment_id' => $specAssessment->getId(),
                    'order'              => ['due_date' => 'asc'],
                    'type'               => AssessmentType::TYPE_DUE_WORK,
                    'work_type'          => self::WORK_TYPE_ASSESSMENT_TASK,
                ]
            );
        }

        // And get the original too (plus all participants)
        $assessments->add($this->assessmentDataSource->findByIdForReview($baseAssessment->getId()));

        $distribution = [
            'grouped'    => [],
            'continuous' => [
                'marks' => [],
            ],
        ];
        foreach ($assessments as $assessment) {
            $studentMarks = [];
            foreach ($assessment->getAllParticipants() as $participant) {
                // Only add mark if not null?
                $normMark = $participant->getNormalisedMark($publishedOnly);
                if (isset($normMark)) {
                    $studentMarks[$participant->getUser()->getId()] = [
                        'mark'        => $normMark,
                        'pretty_mark' => $participant->getHumanReadableMark($publishedOnly),
                    ];
                }
            }

            // What are the possible pretty marks for this assessment?
            // Assume all assessments use the same assessment type, otherwise chaos occurs!
            $markScheme = $assessment->getMarkScheme();
            if ($markScheme instanceof LetterGrade) {
                $levels = $markScheme->getGradeGroup()->getGradeLevels();

                // For now, assume that distributions are only calculated for
                // letter grade assessments.
                foreach ($levels as $level) {
                    $caption = $level->getCaption();
                    if (!empty($caption) && empty($distribution['grouped'][$caption])) {
                        $distribution['grouped'][$caption] = [];
                    }
                }
                foreach ($studentMarks as $item) {
                    $caption = $item['pretty_mark'];
                    if (array_key_exists($caption, $distribution['grouped'])) {
                        $distribution['grouped'][$caption][] = $item;
                    }
                }
            } else {
                // Pretty marks are continuous and hence not to be grouped
                foreach ($studentMarks as $item) {
                    $distribution['continuous']['marks'][] = $item;
                }
            }
        }

        // Calculate mean and SD for continuous distributions
        if (!empty($distribution['continuous']['marks'])) {
            $marks = array_map(
                function ($v) {
                    return (float)$v['mark'];
                },
                $distribution['continuous']['marks']
            );
            $count = count($marks);
            $mean  = array_sum($marks) / $count;
            $sd    = sqrt(
                array_sum(
                    array_map(
                        function ($v) use ($mean) {
                            return ($v - $mean) ** 2;
                        },
                        $marks
                    )
                ) / $count
            );

            // Convert mean and SD to pretty mark
            $markScheme = $baseAssessment->getMarkScheme();
            $prettyMean = $markScheme->getHumanReadableMark($mean);
            $prettySd   = $markScheme->getHumanReadableMark($sd);

            $distribution['continuous']['mean'] = [
                'mark'        => round($mean, 1),
                'pretty_mark' => is_numeric($prettyMean)
                    ? sprintf('%.1f', $prettyMean)
                    : $prettyMean,
            ];
            $distribution['continuous']['sd']   = [
                'mark'        => round($sd, 1),
                'pretty_mark' => is_numeric($prettySd)
                    ? sprintf('%.1f', $prettySd)
                    : $prettySd,
            ];

            // Show marks as series
            // Calculation in PHP is kind of annoying, but...
            $distribution['continuous']['series'] = Statistics::buildBoxPlotValues($marks);
        }
        return $distribution;
    }

    /**
     * Does draft feedback contribute to grades for this report year?
     *
     * It contributes for 2017 and earlier, because teachers are used to having
     * feedback flow through to the eReport without having to publish it.
     *
     * This workflow will change in 2018.
     *
     * @param int $reportYear
     * @return bool
     */
    private function doesDraftFeedbackContribute($reportYear): bool
    {
        return $reportYear < 2018;
    }

    /**
     * Does this subject name refer to what should be a lateral learning report?
     *
     * @param string $subjectName
     * @return bool
     */
    private static function isSubjectLateralLearning(string $subjectName): bool
    {
        return (bool)preg_match(self::LATERAL_LEARNING_SUBJECT_NAME_REGEX, $subjectName);
    }

    /**
     * Base builder for a tutor group report: constructs only the base object.
     *
     * @param int  $level
     * @param User $student
     * @return TutorGroup\Base
     * @throws RangeException if the year level is invalid
     */
    private static function buildTutorGroupReport(int $level, User $student)
    {
        $yearLevel = self::determineYearLevel($level, $student, null);
        if (self::isReportYearMiddleSchool($yearLevel)) {
            $tutorGroup = new TutorGroup\MiddleSchool();
        } elseif (self::isReportYearSeniorSchool($yearLevel)) {
            $tutorGroup = new TutorGroup\SeniorSchool();
        } else {
            // this should've thrown an exception in determineYearLevel,
            // but catch here anyway
            throw new RangeException("Reports are only for years 7-12");
        }
        return $tutorGroup;
    }

    /**
     * Collates all grades given for `$subjectList` to `$student`.
     *
     * @param array       $subjectList
     * @param User        $student
     * @param bool        $publishedOnly
     * @param string|null $roleTypeOverride
     * @return array
     */
    private function collateAllGrades(
        array $subjectList,
        User $student,
        bool $publishedOnly,
        ?string $roleTypeOverride
    ): array {
        $allGrades = [];
        foreach ($subjectList as $subject) {
            $folderWithGrades = $this->getFolderWithGrades(
                $subject->getSubjectCode(),
                $student,
                $publishedOnly,
                $roleTypeOverride
            );
            $allGrades[] = $folderWithGrades['grades'];
        }
        return array_merge(...$allGrades);
    }
}
