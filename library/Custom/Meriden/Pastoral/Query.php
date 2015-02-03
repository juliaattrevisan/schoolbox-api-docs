<?php
/**
 * Queries for the Meriden pastoral system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Custom\Meriden\Pastoral;

use Custom\Meriden\Pastoral\StudentInvolvement\Gateway;
use MSSQL;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Grades;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Query
{
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
     * @var StudentInvolvement\Gateway
     */
    protected $gateway;

    /**
     * Constructor
     *
     * @param MSSQL          $database
     * @param UserMapper     $userMapper
     * @param CategoryMapper $categoryMapper
     * @param Grades         $gradesService
     */
    public function __construct(
        MSSQL $database,
        UserMapper $userMapper,
        CategoryMapper $categoryMapper,
        Grades $gradesService
    ) {
        $this
            ->setGateway(new Gateway($database))
            ->setUserMapper($userMapper)
            ->setCategoryMapper($categoryMapper)
            ->setGradesService($gradesService);
    }

    /**
     * Gets the number of days that this student was absent for this year.
     *
     * The following absence types add the following amount to days absent:
     * * AFS: 1
     * * APD, AAM, APM, EDP: 0.5
     *
     * @param int  $year
     * @param User $student
     *
     * @return float
     */
    public function getAbsenceCount($year, User $student)
    {
        return $this->getGateway()->getAbsenceCount($year, $student);
    }

    /**
     * Gets the number of days that this student was late for this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getLateCount($year, User $student)
    {
        return $this->getGateway()->getLateCount($year, $student);
    }

    /**
     * Gets the number of detentions that this student has had this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getDetentionCount($year, User $student)
    {
        return $this->getGateway()->getDetentionCount($year, $student);
    }

    /**
     * Gets the number of days endorsed leave that this student has had this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getLeaveCount($year, User $student)
    {
        return $this->getGateway()->getLeaveCount($year, $student);
    }

    /**
     * Gets all attendance related counts.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getAllAttendances($year, User $student)
    {
        return array(
            'absent'        => $this->getAbsenceCount($year, $student),
            'late'          => $this->getLateCount($year, $student),
            'detentions'    => $this->getDetentionCount($year, $student),
            'endorsedLeave' => $this->getLeaveCount($year, $student),
        );
    }

    /**
     * Gets awards for the given student.
     *
     * @param      $year
     * @param User $student
     *
     * @return array
     */
    public function getAwards($year, User $student)
    {
        return $this->getGateway()->getAwards($year, $student);
    }

    /**
     * Gets co-curricular activities for the given student.
     *
     * @param      $year
     * @param User $student
     *
     * @return array
     */
    public function getCocurricular($year, User $student)
    {
        return $this->getGateway()->getCocurricular($year, $student);
    }

    /**
     * Gets community service for the given student.
     *
     * @param      $year
     * @param User $student
     *
     * @return array
     */
    public function getService($year, User $student)
    {
        return $this->getGateway()->getService($year, $student);
    }

    /**
     * Gets positions of offices for the given student.
     *
     * @param      $year
     * @param User $student
     *
     * @return array
     */
    public function getPositions($year, User $student)
    {
        return $this->getGateway()->getPositions($year, $student);
    }

    /**
     * Gets all activities (awards, co-curricular, community service, positions
     * of office).
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getAllActivities($year, User $student)
    {
        return array(
            'awards'       => $this->getAwards($year, $student),
            'cocurricular' => $this->getCocurricular($year, $student),
            'service'      => $this->getService($year, $student),
            'positions'    => $this->getPositions($year, $student),
        );
    }

    /**
     * Gets all tutors this student may have had through the year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getAllTutors($year, User $student)
    {
        return $this->getGateway()->getAllTutors($year, $student);
    }

    /**
     * Retrieves all year level groups for this student.
     *
     * @param User $student
     *
     * @return array
     */
    public function getAllYearLevelGroups(User $student)
    {
        $result = $this->getGateway()->getAllYearLevelGroups($student);
        return array_map(
            array($this, 'augmentSubjectWithCode'),
            $result
        );
    }

    /**
     * Given a year level and a student, gets their year level group for that year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getYearLevelGroup($year, User $student)
    {
        $result = $this->getGateway()->getYearLevelGroup($year, $student);
        return $this->augmentSubjectWithFolder(
            $this->augmentSubjectWithCode($result),
            $student
        );
    }

    /**
     * Given a year level and a student, gets their year level for that year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return int
     */
    public function getYearLevelForStudentForYear($year, User $student)
    {
        $subject = $this->getYearLevelGroup($year, $student);
        return $subject['year_level'];
    }

    /**
     * Adds Schoolbox compatible subject code into the subject record.
     *
     * @param array $subject
     *
     * @return array
     */
    protected function augmentSubjectWithCode($subject)
    {
        $subject['code'] = sprintf(
            '-%s-%s',
            $subject['sub_code'],
            $subject['class']
        );
        return $subject;
    }

    /**
     * Gets all subjects for this student for this year.
     *
     * @param int  $year
     * @param User $student
     *
     * @return array
     */
    public function getAllSubjectsForYear($year, User $student)
    {
        $result = $this->getGateway()->getAllSubjectsForYear($year, $student);

        // Add class code
        // year level is the student's year level at the time they took class,
        // not the year in which they took it.
        $result = array_map(
            array($this, 'augmentSubjectWithCode'),
            $result
        );

        // Join all teacher codes into a single result.
        $result = array_reduce(
            $result,
            function ($carry, $newRow) {
                $isDuplicateCode = false;
                foreach ($carry as &$row) {
                    if ($row['code'] == $newRow['code']) {
                        $row['tch_code'][] = $newRow['tch_code'];
                        $isDuplicateCode = true;
                        break;
                    }
                }
                unset($row);
                if (!$isDuplicateCode) {
                    $newRow['tch_code'] = array($newRow['tch_code']);
                    $carry[] = $newRow;
                }
                return $carry;
            },
            array()
        );
        $result = array_map(
            array($this, 'augmentSubjectWithTeacher'),
            $result
        );
        return $result;
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
     * Gets the subject outlines for a given subject code.
     *
     * @param string $subjectCode
     *
     * @return array
     */
    public function getSubject($subjectCode)
    {
        $result = $this->getGateway()->getSubject($subjectCode);
        $result = $this->augmentSubjectWithCode($result);
        return $result;
    }

    /**
     * Adds the folder object into the subject.
     *
     * @param array $subject
     * @param User  $student
     *
     * @return array
     */
    protected function augmentSubjectWithFolder($subject, User $student)
    {
        $folderWithGrades  = $this->getFolderWithGrades($subject['code'], $student);
        $subject['folder'] = $folderWithGrades['folder'];
        $subject['grades'] = $folderWithGrades['grades'];
        return $subject;
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
    public function getFolderWithGrades($subjectCode, User $student)
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
    public function getStudentInvolvement(Category $folder, User $student)
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
}
