<?php
/**
 * Factory for creating tutor group factory objects
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Mapper\Exception as MapperException;
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
                    [$this, 'augmentSubjectWithTeacher'],
                    $this->gateway->getAllSubjectsForYear($year, $student)
                )
            );

        $tutorGroupCode   = $tutorGroup->getTutorGroup();
        $folderWithGrades = $this->getFolderWithGrades($tutorGroupCode['code'], $student);
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

        $folderWithGrades = $this->getFolderWithGrades($subjectCode, $student);
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

        // Add grade distributions
        $newGrades = [];
        foreach ($subject->getGrades() as $key => $grade) {
            $grade['distribution'] = $this->buildDistribution(
                $grade,
                $subject->getCohortFolders()
            );
            $newGrades[$key]       = $grade;
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
            $involvement = $this->getStudentInvolvement($folder, $student);
            if ($involvement) {
                return [
                    'folder' => $folder,
                    'grades' => $involvement['grades'],
                ];
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
            $involvement = $this->getStudentInvolvement($archivedFolder, $student);
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
                    return [
                        'grades' => $this->getGrades($folder, $student),
                    ];
                }
            }
        }

        // If not, has $student got a grade in this folder?
        $grades = $this->getGrades($folder, $student);
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
     *
     * @return array
     */
    protected function getGrades(Folder $folder, User $student)
    {
        $allGrades = [];

        // Only due works are shown
        // Not interested in work types other than assessment tasks
        foreach ($this->assessmentDataSource->findAllByFolderIdForReview(
            $folder->getId(),
            $student,
            [
                'order'     => ['due_date' => 'asc'],
                'type'      => AssessmentType::TYPE_DUE_WORK,
                'work_type' => self::WORK_TYPE_ASSESSMENT_TASK,
            ]
        ) as $assessment) {
            // Not interested in non-dueworks
            if (!$assessment instanceof DueWork) {
                continue;
            }

            $participant = $assessment->getParticipantIfExtant($student);
            if ($participant) {
                $feedback = $participant->getAuthoritativeFeedback(true);
                if ($feedback) {

                    // Load rubrics into assessment whilst here
                    $allGrades['a' . $assessment->getId()] = [
                        'assessment'  => [
                            'id'      => $assessment->getId(),
                            'name'    => $assessment->getTitle(),
                            'dueDate' => $assessment->getDueAt(),
                            'rubric'  => $this->rubricMapper->findByDueWork($assessment)->toContext(),
                        ],
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
     * @param array          $grade
     * @param array|Folder[] $cohortFolders
     *
     * @return array
     */
    public function buildDistribution($grade, $cohortFolders)
    {
        if (!($grade['assessment'] instanceof DueWork)) {
            return [];
        }

        // Get cohort for this class
        // Original implementation searched on assessment name,
        // but we can use unit spec ID here.
        $assessments    = [];
        $specAssessment = $grade['assessment']->getSpecificationAssessment();
        if ($specAssessment) {
            $assessments = $this->assessmentDataSource->findAllByFolderIdForReview(
                $cohortFolders,
                null,
                [
                    'spec_assessment_id' => $specAssessment->getId(),
                    'order'              => ['due_date' => 'asc'],
                    'type'               => AssessmentType::TYPE_DUE_WORK,
                    'work_type'          => self::WORK_TYPE_ASSESSMENT_TASK,
                ]
            );
        } else {
            $assessments[] = $this->assessmentDataSource->findByIdForReview($grade['assessment']->getId());
        }

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
                $normMark = $participant->getNormalisedMark(true);
                if (isset($normMark)) {
                    $studentMarks[$participant->getUser()->getId()] = [
                        'mark'        => $normMark,
                        'pretty_mark' => $participant->getHumanReadableMark(true),
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
                    return $v['mark'];
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
            $markScheme = $grade['assessment']->getMarkScheme();
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
        }
        return $distribution;
    }
}
