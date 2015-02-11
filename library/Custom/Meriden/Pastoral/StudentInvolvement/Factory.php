<?php
/**
 * Factory for creating tutor group factory objects
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Grades;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Factory
{
    /**
     * @var Gateway
     */
    protected $gateway;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var CategoryMapper
     */
    protected $categoryMapper;

    /**
     * @var Grades
     */
    protected $gradesService;

    /**
     * Constructor
     *
     * @param Gateway        $gateway
     * @param UserMapper     $userMapper
     * @param CategoryMapper $categoryMapper
     * @param Grades         $gradesService
     */
    public function __construct(
        Gateway $gateway,
        UserMapper $userMapper,
        CategoryMapper $categoryMapper,
        Grades $gradesService
    ) {
        // Set up required mappers, DB access, etc. here.
        $this
            ->setGateway($gateway)
            ->setUserMapper($userMapper)
            ->setCategoryMapper($categoryMapper)
            ->setGradesService($gradesService)
        ;
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
            ->setActivities($this->getGateway()->getAllActivities($year, $student))
            ->setAttendances($this->getGateway()->getAllAttendances($year, $student))
            ->setTutors($this->getGateway()->getAllTutors($year, $student))
            ->setTutorGroup($this->getGateway()->getYearLevelGroup($year, $student))
            // Add teachers into subjects - debatable if this is part of tutor group?
            ->setSubjects(
                array_map(
                    array($this, 'augmentSubjectWithTeacher'),
                    $this->getGateway()->getAllSubjectsForYear($year, $student)
                )
            )
        ;

        $tutorGroupCode   = $tutorGroup->getTutorGroup();
        $folderWithGrades = $this->getFolderWithGrades($tutorGroupCode['code'], $student);
        $tutorGroup
            ->setSubjectCode($tutorGroupCode['code'])
            ->setFolder($folderWithGrades['folder'])
            ->setGrades($this->augmentGradesWithAssessment($folderWithGrades['grades'], $student))
        ;
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
        $externalData = $this->getGateway()->getSubject($subjectCode);
        $subject
            ->setName($externalData['sub_desc'])
            ->setDescription($externalData['outline_text'])
            ->setStudent($student)
            ->setYear($year)
            ->setSubjectCode($subjectCode)
        ;

        $folderWithGrades = $this->getFolderWithGrades($subjectCode, $student);
        $subject
            ->setFolder($folderWithGrades['folder'])
            ->setGrades($this->augmentGradesWithAssessment($folderWithGrades['grades'], $student))
            ->moveToNamedGrade('courseGrade', 'Course Grade')
            ->moveToNamedGrade('endeavourGrades', 'Endeavour Grades')
            ->moveToNamedGrade('learningApproaches', 'Approaches to Learning')
        ;
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
        foreach ($this->getGateway()->getAllYearLevelGroups($student) as $level) {
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
            $this->getGateway()->getAllSubjectsForYear($year, $student)
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
     *  folder: Category
     *  grades: array
     * }
     */
    protected function getFolderWithGrades($subjectCode, User $student)
    {
        try {
            // Find by category code, and figure out if they're involved.
            $folder = $this->getCategoryMapper()->findByCode($subjectCode);
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
        $archivedFolders = $this->getCategoryMapper()->findAllByArchivedCode($subjectCode);
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
            'folder' => $this->getCategoryMapper()->createModel(),
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
     * @param Category $folder
     * @param User     $student
     *
     * @return array {
     *  grades: array (if the student is involved)
     * }
     */
    protected function getStudentInvolvement(Category $folder, User $student)
    {
        // Is $student a member of $folder (by class code?)
        $studentId = $student->getId();
        foreach ($student->getSubjectCollection() as $subject) {
            foreach ($folder->getCodes() as $code) {
                if ($code->getCode() == $subject['code']) {
                    // Student is involved, even if they have no grades.
                    // Try to get them anyway.
                    $grades = ReturnedWorkMapper::getInstance()->getStudentMarks(
                        $folder->getId(),
                        $studentId
                    );
                    return array(
                        'grades' => $grades ?: array(),
                    );
                }
            }
        }

        // If not, has $student got a grade in this folder?
        $grades = ReturnedWorkMapper::getInstance()->getStudentMarks(
            $folder->getId(),
            $studentId
        );
        if (!empty($grades)) {
            return array(
                'grades' => $grades,
            );
        }
        return array();
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
        // TODO get rid of the handlers too
        $rubricHandler = new \DueWork_Rubric_Handler();
        foreach (SubmissionBoxMapper::getInstance()->findByColumn('id', array_keys($grades)) as $assess) {
            /** @var SubmissionBox $assess */
            $assessId = $assess->getId();
            $grades[$assessId]['assessment'] = $assess;
            $grades[$assessId]['rubric']     = array();
            // Is there a rubric involved? If so get student's marks for it
            if ($assess->isRubric()) {
                $rubric     = $rubricHandler->getRubric($assessId);
                $rubricMark = new \DueWork_Rubric_Mark($rubric);
                $rubricMark->populateMarks($student->getId());
                $grades[$assessId]['rubric'] = $rubricMark->toArray();
            }
        }
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
        $subject['teachers'] = $this->getUserMapper()->findByColumn('synergy_id', $subject['tch_code']);
        return $subject;
    }

    /**
     * @return Gateway
     */
    public function getGateway()
    {
        return $this->gateway;
    }

    /**
     * @param  Gateway $gateway
     *
     * @return $this
     */
    public function setGateway($gateway)
    {
        $this->gateway = $gateway;
        return $this;
    }

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param  UserMapper $userMapper
     *
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return CategoryMapper
     */
    public function getCategoryMapper()
    {
        return $this->categoryMapper;
    }

    /**
     * @param  CategoryMapper $categoryMapper
     *
     * @return $this
     */
    public function setCategoryMapper($categoryMapper)
    {
        $this->categoryMapper = $categoryMapper;
        return $this;
    }

    /**
     * @return Grades
     */
    public function getGradesService()
    {
        return $this->gradesService;
    }

    /**
     * @param  Grades $gradesService
     *
     * @return $this
     */
    public function setGradesService($gradesService)
    {
        $this->gradesService = $gradesService;
        return $this;
    }
}
