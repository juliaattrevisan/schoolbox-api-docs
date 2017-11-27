<?php
/**
 * Factory for creating tutor group factory objects
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Factory
{
    // This is pretty fragile. Must match value configured in Admin WorkType lists
    const WORK_TYPE_ASSESSMENT_TASK = 1;

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
     * @var RubricMapper
     */
    private $rubricMapper;

    /**
     * Constructor
     *
     * @param Gateway              $gateway
     * @param UserMapper           $userMapper
     * @param FolderMapper         $folderMapper
     * @param AssessmentDataSource $assessmentDataSource
     * @param RubricMapper         $rubricMapper
     */
    public function __construct(
        Gateway $gateway,
        UserMapper $userMapper,
        FolderMapper $folderMapper,
        AssessmentDataSource $assessmentDataSource,
        RubricMapper $rubricMapper
    ) {
        // Set up required mappers, DB access, etc. here.
        $this->gateway              = $gateway;
        $this->userMapper           = $userMapper;
        $this->folderMapper         = $folderMapper;
        $this->assessmentDataSource = $assessmentDataSource;
        $this->rubricMapper         = $rubricMapper;
    }

    /**
     * Builds a TutorGroup object
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
                    array($this, 'augmentSubjectWithTeacher'),
                    $this->gateway->getAllSubjectsForYear($year, $student)
                )
            )
        ;

        $tutorGroupCode   = $tutorGroup->getTutorGroup();
        $folderWithGrades = $this->getFolderWithGrades($tutorGroupCode['code'], $student);
        $tutorGroup
            ->setSubjectCode($tutorGroupCode['code'])
            ->setFolder($folderWithGrades['folder'])
            ->setGrades($this->augmentGradesWithAssessment($folderWithGrades['grades'], $student))
            ->moveToNamedGrade('athletics', 'Athletics Carnival')
            ->moveToNamedGrade('swimming', 'Swimming Carnival')
            ->moveToNamedGrade('camp', 'School Camp')
            ->moveToNamedGrade('uniform', 'Uniform')
            ->moveToNamedGrade('pastoral', 'Pastoral activities involvement')
            ->moveToNamedGrade('tutorComment', 'Tutor Comment')
        ;

        // Add write level staff as coordinators
        $folder = $tutorGroup->getFolder();
        if ($folder instanceof Folder) {
            $tutorGroup->setCoordinators(
                UserAccessMapper::getInstance()->findUsersInCategory(
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
     * Builds a Subject object
     *
     * @param int    $year
     * @param User   $student
     * @param string $subjectCode
     *
     * @return Subject
     */
    public function buildSubject($year, User $student, $subjectCode)
    {
        $subject = new Subject();
        $externalData = $this->gateway->getSubject($year, $subjectCode);
        $externalData = $this->augmentSubjectWithTeacher($externalData);
        $subject
            ->setName($externalData['sub_desc'])
            ->setDescription($externalData['outline_text'])
            ->setTeachers($externalData['teachers'])
            ->setStudent($student)
            ->setYear($year)
            ->setSubjectCode($subjectCode)
        ;

        $folderWithGrades = $this->getFolderWithGrades($subjectCode, $student);
        $subject
            ->setFolder($folderWithGrades['folder'])
            ->setCohortFolders($this->getCohortFolders($folderWithGrades['folder'], $subjectCode))
            ->setGrades($this->augmentGradesWithAssessment($folderWithGrades['grades'], $student))
            ->moveToNamedGrade('courseGrade', 'Course Grade')
            ->moveToNamedGrade('endeavourGrades', 'Endeavour Grades')
            ->moveToNamedGrade('endeavourGrade1', 'Endeavour Grade Term 1')
            ->moveToNamedGrade('endeavourGrade2', 'Endeavour Grade Term 2')
            ->moveToNamedGrade('endeavourGrade3', 'Endeavour Grade Term 3')
            ->moveToNamedGrade('endeavourGrade4', 'Endeavour Grade Term 4')
            ->moveToNamedGrade('learningApproaches', 'Approaches to Learning')
        ;

        // Add grade distributions
        $newGrades = array();
        foreach ($subject->getGrades() as $key => $grade) {
            $grade['distribution'] = $this->buildDistribution(
                $grade,
                $subject->getCohortFolders()
            );
            $newGrades[$key] = $grade;
        }
        $subject->setGrades($newGrades);

        // What are the other grade distributions?
        $courseGrade = $subject->getNamedGrade('courseGrade');
        if (!empty($courseGrade)) {
            $courseGrade['distribution'] = $this->buildDistribution(
                $courseGrade,
                $subject->getCohortFolders()
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
        $tutorGroups = array();
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
        $subjects = array();
        $result   = array_map(
            array($this, 'augmentSubjectWithTeacher'),
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
     *
     * @return array {
     *  folder: Folder
     *  grades: array
     * }
     */
    protected function getFolderWithGrades($subjectCode, User $student)
    {
        try {
            // Find by category code, and figure out if they're involved.
            $folder = $this->folderMapper->findByCode($subjectCode);
            if ($involvement = $this->getStudentInvolvement($folder, $student)) {
                return array(
                    'folder' => $folder,
                    'grades' => $involvement['grades'],
                );
            }
        } catch (MapperException $e) {
            // no folder with this cat code exists.
            // Try to find by archived code instead.
        }

        // Find by archived code, as this folder might have been rolled over.
        // Multiple rolled-over categories with this code could exist,
        // get the one that has the requested student in it.
        // To do this, every matching category has to be checked.
        $archivedFolders = $this->folderMapper->findAllByArchivedCode($subjectCode);
        foreach ($archivedFolders as $archivedFolder) {
            if ($involvement = $this->getStudentInvolvement($archivedFolder, $student)) {
                return array(
                    'folder' => $archivedFolder,
                    'grades' => $involvement['grades'],
                );
            }
        }

        // everything failed? return an empty model
        return array(
            'folder' => $this->folderMapper->createModel(),
            'grades' => array(),
        );
    }

    /**
     * Is $student involved in $folder?
     *
     * This performs the following checks:
     * * Is this student currently a member of the class?
     * * Does this student have a grade in this folder?
     *
     * @param Folder $folder
     * @param User     $student
     *
     * @return array {
     *  grades: array (if the student is involved)
     * }
     */
    protected function getStudentInvolvement(Folder $folder, User $student)
    {
        // Is $student a member of $folder (by class code?)
        foreach ($student->getSubjectCollection() as $subject) {
            foreach ($folder->getCodes() as $code) {
                if ($code->getCode() == $subject['code']) {
                    // Student is involved, even if they have no grades.
                    // Try to get them anyway.
                    return array(
                        'grades' => $this->getGrades($folder, $student),
                    );
                }
            }
        }

        // If not, has $student got a grade in this folder?
        $grades = $this->getGrades($folder, $student);
        if (!empty($grades)) {
            return array(
                'grades' => $grades,
            );
        }
        return array();
    }

    /**
     * Gets all assessment and quiz grades.
     *
     * @param Folder $folder
     * @param User     $student
     *
     * @return array
     */
    protected function getGrades(Folder $folder, User $student)
    {
        $allGrades = [];

        // Only due works are shown
        foreach ($this->assessmentDataSource->findAllByFolderIdForReview(
            $folder->getId(),
            $student,
            [
                'order' => ['a.due_date' => 'asc'],
                'type'  => AssessmentType::TYPE_DUE_WORK,
            ]
        ) as $assessment) {
            $participant = $assessment->getParticipantIfExtant($student);
            if ($participant) {
                $feedback = $participant->getAuthoritativeFeedback(true);
                if ($feedback) {
                    $allGrades['a'.$assessment->getId()] = [
                        'assessment'  => [
                            'id'      => $assessment->getId(),
                            'name'    => $assessment->getTitle(),
                            'dueDate' => $assessment->getDueAt(),
                        ],
                        'pretty_mark' => $feedback->getHumanReadableMark(),
                        'mark'        => $feedback->getNormalisedMark(),
                        'comment'     => $feedback->getComment(),
                    ];
                }
            }
        }
        return $allGrades;

//        $allGrades = array();
//        $assessGrades = ReturnedWorkMapper::getInstance()->getStudentMarks(
//            $folder->getId(),
//            $student->getId()
//        ) ?: array();
//        $quizGrades = QuizAttemptMapper::getInstance()->getStudentMarks(
//            $folder->getId(),
//            $student->getId()
//        ) ?: array();
//        foreach ($assessGrades as $id => $grade) {
//            $allGrades['a'.$id] = $grade;
//        }
//        foreach ($quizGrades as $id => $grade) {
//            $allGrades['q'.$id] = $grade;
//        }
//        // Sort grades too
//        uksort(
//            $allGrades,
//            function ($a, $b) {
//                return $a['date'] < $b['date'] ? -1 : 1;
//            }
//        );
//        return $allGrades;
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
        $cohortList = array();
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
     * Adds assessment into grades fetched by getStudentInvolvement().
     *
     * @param array $grades
     * @param User  $student
     *
     * @return array
     */
    protected function augmentGradesWithAssessment($grades, User $student)
    {
        // TODO inject dependencies

        // Get the 'a' off the start of the assessment grades to get a list of
        // actual assess IDs
        $assessKeys = array();
        $quizKeys   = array();
        foreach (array_keys($grades) as $key) {
            $type = substr($key, 0, 1);
            $id   = substr($key, 1);
            if ($type === 'a') {
                $assessKeys[] = $id;
            } elseif ($type === 'q') {
                $quizKeys[] = $id;
            }
        }


        // Fetch only assessment tasks
        $assessments = SubmissionBoxMapper::getInstance()->findByColumnList([
            'id'   => $assessKeys,
//            'type' => self::WORK_TYPE_ASSESSMENT_TASK,
        ]);
        foreach ($assessments as $assess) {
            /** @var SubmissionBox $assess */
            $assessId = $assess->getId();
            $gradeKey = 'a'.$assessId;
            $grades[$gradeKey]['assessment'] = $assess;
            $grades[$gradeKey]['rubric']     = array();
            // Is there a rubric involved? If so get student's marks for it
            if ($assess->isRubric()) {
                $grid = SubmissionBox\Rubric\RubricMapper::getInstance()->findBySubmissionBoxAndStudent($assess, $student);
                $grades[$gradeKey]['rubric'] = $grid->toContext();
            }
        }
        // No quizzes anymore
//        foreach (QuizMapper::getInstance()->findByColumn('assess_id', $quizKeys) as $quiz) {
//            /** @var Quiz $quiz */
//            $grades['q'.$quiz->getId()]['quiz'] = $quiz;
//        }

        // Sort assessments by due date
        uasort(
            $grades,
            function($a, $b) {
                $assessA = $a['assessment'];
                $assessB = $b['assessment'];
                if ($assessA instanceof SubmissionBox && $assessB instanceof SubmissionBox) {
                    return $assessA->getDueDate()->compare($assessB->getDueDate());
                }
                return 0;
            }
        );
        return $grades;
    }

    /**
     * Augments a subject retrieved by getAllSubjectsForYear() with one or
     * more teachers.
     *
     * @param array $subject
     *
     * @return array
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
     * @param array            $grade
     * @param array|Folder[] $cohortFolders
     *
     * @return array
     */
    protected function buildDistribution($grade, $cohortFolders)
    {
        if (!($grade['assessment'] instanceof SubmissionBox)) {
            return array();
        }
        $boxMapper          = SubmissionBoxMapper::getInstance();
        $returnedWorkMapper = ReturnedWorkMapper::getInstance();

        // Get the matching assessments in the cohort classes
        $cohortFolderIds = array();
        foreach ($cohortFolders as $cohortFolder) {
            $cohortFolderIds[] = $cohortFolder->getId();
        }
        $assessmentName = $grade['assessment']->getName();
        $assessments    = $boxMapper->findByColumnList(
            array(
                'name'     => $assessmentName,
                'category' => $cohortFolderIds,
//                'type'     => self::WORK_TYPE_ASSESSMENT_TASK,
            )
        );
        $assessments->add($grade['assessment']);

        $distribution = array(
            'grouped'    => array(),
            'continuous' => array(
                'marks' => array(),
            ),
        );
        foreach ($assessments as $assessment) {
            // Get everyone who has submitted to this assessment
            $returnStudents = array();
            foreach ($returnedWorkMapper->findByColumn('box_id', $assessment->getId()) as $return) {
                $returnStudentId = $return->getOwner()->getId();
                $returnStudents[$returnStudentId] = $returnStudentId;
            }

            // Slurp out all student marks for this folder, and put back the one we
            // actually want if it's there.
            $studentMarks = array();
            foreach ($returnStudents as $studentId) {
                $marks = $returnedWorkMapper->getStudentMarks(
                    $assessment->getCategory()->getId(),
                    $studentId
                );
                if (array_key_exists($assessment->getId(), $marks)) {
                    $studentMarks[$studentId] = $marks[$assessment->getId()];
                }
            }

            // What are the possible pretty marks for this assessment?
            // Assume all assessments use the same assessment type, otherwise chaos occurs!
            if ($assessment->getMarkType()->getId() == SubmissionBox\MarkType::TYPE_LETTER) {
                $levels = SubmissionBox\GradeLevel\Mapper::getInstance()
                    ->findByColumn(
                        'submission_grade_group_id',
                        $assessment->getGradeGroup()->getId(),
                        array(
                            'order' => array('raw_grade' => 'desc')
                        )
                    );

                // For now, assume that distributions are only calculated for
                // letter grade assessments.
                foreach ($levels as $level) {
                    $caption = $level->getCaption();
                    if (!empty($caption) && empty($distribution['grouped'][$caption])) {
                        $distribution['grouped'][$caption] = array();
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
                $distribution['continuous']['marks'] = array_merge(
                    $distribution['continuous']['marks'],
                    $studentMarks
                );
            }
        }

        // Calculate mean and SD for continuous distributions
        if (!empty($distribution['continuous']['marks'])) {
            $marks = array_map(
                function ($v) {
                    return $v['mark'];
                },
                $distribution['continuous']['marks']
            );
            $count = count($marks);
            $mean  = array_sum($marks) / $count;
            $sd    = sqrt(
                array_sum(
                    array_map(
                        function ($v) use ($mean) {
                            return pow($v - $mean, 2);
                        },
                        $marks
                    )
                ) / $count
            );

            // Convert mean and SD to pretty mark
            $markType  = SubmissionBox\MarkTypeMapper::getInstance()->findById(
                $grade['assessment']->getMarkType()->getId()
            );
            $converter = \Marksheet_Factory::build(
                $markType->getSlug(),
                $grade['assessment']
            );
            $maxMark    = $grade['assessment']->getMaxMark();
            $prettyMean = $converter->convertRawMark($mean, $maxMark);
            $prettySd   = $converter->convertRawMark($sd, $maxMark);
            $distribution['continuous']['mean'] = array(
                'mark'        => round($mean, 1),
                'pretty_mark' => is_numeric($prettyMean)
                    ? sprintf('%.1f', $prettyMean)
                    : $prettyMean
            );
            $distribution['continuous']['sd'] = array(
                'mark'        => round($sd, 1),
                'pretty_mark' => is_numeric($prettySd)
                    ? sprintf('%.1f', $prettySd)
                    : $prettySd
            );
        }
        return $distribution;
    }
}
