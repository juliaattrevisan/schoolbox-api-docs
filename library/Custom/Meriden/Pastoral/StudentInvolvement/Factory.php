<?php
/**
 * Factory for creating tutor group factory objects
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Alaress\Statistics\Statistics;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Factory
{
    // This is pretty fragile. Must match value configured in Admin WorkType lists
    private const WORK_TYPE_ASSESSMENT_TASK = 1;

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
     * Access permissions data source
     *
     * @var Access
     */
    private $access;

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
     * @param Access               $access
     * @param UserAccessMapper     $userAccessMapper
     */
    public function __construct(
        Gateway $gateway,
        UserMapper $userMapper,
        FolderMapper $folderMapper,
        AssessmentDataSource $assessmentDataSource,
        RubricDataSource $rubricMapper,
        User $currentUser,
        Access $access,
        UserAccessMapper $userAccessMapper
    ) {
        $this->gateway              = $gateway;
        $this->userMapper           = $userMapper;
        $this->folderMapper         = $folderMapper;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->rubricMapper         = $rubricMapper;
        $this->currentUser          = $currentUser;
        $this->access               = $access;
        $this->userAccessMapper     = $userAccessMapper;
    }

    /**
     * Builds a TutorGroup object for a given year and student.
     *
     * This is used as a data store for the year level eReport.
     *
     * @param int  $year
     * @param User $student
     *
     * @return TutorGroup
     */
    public function buildTutorGroup($year, User $student)
    {
        $tutorGroup = new TutorGroup();
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
        $folderWithGrades = $this->getFolderWithGrades($tutorGroupCode['code'], $student, $publishedOnly);
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
        if ($folder instanceof Folder) {
            $tutorGroup->setCoordinators(
                $this->userAccessMapper->findUsersInCategory(
                    $folder,
                    Role::STAFF,
                    UserAccess::ACCESS_WRITE,
                    false
                )
            );
        }
        return $tutorGroup;
    }

    /**
     * Builds a Subject object for a given year, student and subject.
     *
     * This is used as a data store for the subject eReport.
     *
     * @param int    $year
     * @param User   $student
     * @param string $subjectCode
     *
     * @return Subject
     */
    public function buildSubject($year, User $student, $subjectCode)
    {
        $subject      = new Subject();
        $externalData = $this->gateway->getSubject($year, $subjectCode);
        $externalData = $this->augmentSubjectWithTeacher($externalData);
        $subject
            ->setName($externalData['sub_desc'])
            ->setDescription($externalData['outline_text'])
            ->setTeachers($externalData['teachers'])
            ->setStudent($student)
            ->setYear($year)
            ->setSubjectCode($subjectCode);

        // Add grades for the class page
        $publishedOnly    = !$this->doesDraftFeedbackContribute($year);
        $folderWithGrades = $this->getFolderWithGrades($subjectCode, $student, $publishedOnly);
        $subject
            ->setFolder($folderWithGrades['folder'])
            ->setCohortFolders($this->getCohortFolders($folderWithGrades['folder'], $subjectCode))
            ->setGrades($folderWithGrades['grades'])
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
     * Builds a list of all of the tutor groups that this student has been in.
     *
     * They will be populated with only a year of study and student.
     *
     * @param User $student
     *
     * @return array|TutorGroup[]
     */
    public function buildTutorGroupList(User $student)
    {
        $tutorGroups = [];
        foreach ($this->gateway->getAllYearLevelGroups($student) as $level) {
            $tutorGroup = new TutorGroup();
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
     * @return array|Subject[]
     */
    public function buildSubjectList($year, User $student)
    {
        $subjects = [];
        $result   = array_map(
            [$this, 'augmentSubjectWithTeacher'],
            $this->gateway->getAllSubjectsForYear($year, $student)
        );
        foreach ($result as $row) {
            $subject = new Subject();
            $subject
                ->setName($row['sub_desc'])
                ->setStudent($student)
                ->setYear($row['yr_study'])
                ->setSubjectCode($row['code'])
                ->setTeachers($row['teachers']);
            $subjects[$row['code']] = $subject;
        }
        return $subjects;
    }

    /**
     * Gets the (current or archived) folder for this subject code and student,
     * or an empty one if not found.
     *
     * @param string $subjectCode
     * @param User   $student
     * @param bool  $publishedOnly
     *
     * @return array {
     *  folder: Folder
     *  grades: array
     * }
     */
    protected function getFolderWithGrades($subjectCode, User $student, bool $publishedOnly)
    {
        // Find by category code, and figure out if they're involved.
        $folder = $this->folderMapper->findForCampusAndClass('', $subjectCode);
        if ($folder) {
            $involvement = $this->getStudentInvolvement($folder, $student, $publishedOnly);
            if ($involvement) {
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
            $involvement = $this->getStudentInvolvement($archivedFolder, $student, $publishedOnly);
            if ($involvement) {
                return [
                    'folder' => $archivedFolder,
                    'grades' => $involvement['grades'],
                ];
            }
        }

        // everything failed? return an empty model
        return [
            'folder' => $this->folderMapper->createModel(),
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
     * @param Folder $folder
     * @param User   $student
     * @param bool   $publishedOnly
     *
     * @return array {
     *  grades: array (if the student is involved)
     * }
     */
    protected function getStudentInvolvement(Folder $folder, User $student, bool $publishedOnly)
    {
        // Is $student a member of $folder (by class code?)
        foreach ($student->getSubjectCollection() as $subject) {
            foreach ($folder->getCodes() as $code) {
                if ($code->getCode() == $subject['code']) {
                    // Student is involved, even if they have no grades.
                    // Try to get them anyway.
                    return [
                        'grades' => $this->getGrades($folder, $student, $publishedOnly),
                    ];
                }
            }
        }

        // If not, has $student got a grade in this folder?
        $grades = $this->getGrades($folder, $student, $publishedOnly);
        if (!empty($grades)) {
            return [
                'grades' => $grades,
            ];
        }
        return [];
    }

    /**
     * Gets all assessment and quiz grades.
     *
     * @param Folder $folder
     * @param User   $student
     * @param bool   $publishedOnly
     *
     * @return array {
     *  assessment:  IAssessable
     *  rubric:      array {
     *    groups: {
     *      criteria: {
     *        name: string
     *          descriptors: {
     *            selected: bool
     *            text:     string
     *        }
     *      }
     *    }
     *  }
     *  pretty_mark: string
     *  mark:        float
     *  comment:     string
     * }
     */
    protected function getGrades(Folder $folder, User $student, bool $publishedOnly)
    {
        $allGrades = [];

        // Only due works are shown
        // Not interested in work types other than assessment tasks
        foreach ($this->assessmentDataSource->findAllByFolderIdForReview(
            $folder->getId(),
            $student,
            ['order' => ['due_date' => 'asc'],]
        ) as $assessment) {

            $participant = $assessment->getParticipantIfExtant($student);
            if ($participant) {
                $feedback = $participant->getAuthoritativeFeedback($publishedOnly);
                $rubric   = null;
                if ($assessment instanceof DueWork
                    && $feedback instanceof DueWorkFeedback
                    && $assessment->getUseRubric()
                ) {
                    $rubric = $this->rubricMapper->retrieveMarkedGrid($feedback);
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
    protected function getCohortFolders(Folder $category, $subjectCode)
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
            } catch (\Exception $e) {
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
    protected function augmentSubjectWithTeacher(array $subject)
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
    public function buildDistribution($grade, $cohortFolders, bool $publishedOnly)
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
            $count = \count($marks);
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
    private function doesDraftFeedbackContribute($reportYear) : bool
    {
        return $reportYear < 2018;
    }
}
