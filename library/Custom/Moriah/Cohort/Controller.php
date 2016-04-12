<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Course;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

/**
 * Controller for Moriah's cohort page.
 *
 * @package Custom\Moriah\Cohort
 */
class Controller extends Base
{
    /**
     * @var Folder\Mapper
     */
    protected $folderMapper;

    /**
     * @var Course\UnitInstanceMapper
     */
    protected $unitInstanceMapper;

    /**
     * Constructor
     *
     * @param Folder\Mapper             $folderMapper
     * @param Course\UnitInstanceMapper $unitInstanceMapper
     */
    public function __construct(Folder\Mapper $folderMapper, Course\UnitInstanceMapper $unitInstanceMapper)
    {
        $this->folderMapper       = $folderMapper;
        $this->unitInstanceMapper = $unitInstanceMapper;
    }

    /**
     * Action for displaying a list of courses which a student is involved in.
     *
     * @param User $student
     */
    public function courseList(User $student)
    {
        if (!$student->getRole()->isStudent()) {
            // Are they even students?
            throw new \RangeException('This report is only for students');
        }
        $subjects = $this->folderMapper->findForUser($student);
        // Sort by name
        $subjects->sortByCallback(
            function($a, $b) {
                /** @var Folder\Model $a */
                /** @var Folder\Model $b */
                $result = strnatcasecmp($a->getNameWithCodes(), $b->getNameWithCodes());
                if (!$result) {
                    return strnatcasecmp($a->getId(), $b->getId());
                }
                return $result;
            }
        );
        // Load all courses associated with these subjects
        $courses = $this->unitInstanceMapper->findAllCoursesUsedInFolders($subjects->getColumnValues('id'));
        $this->getResponse()->content->set([
            'format'   => 'html',
            'template' => '@customMoriahCohort/courseList.twig',
            'data'     => [
                'student'  => $student,
                'subjects' => $subjects,
                'courses'  => $courses,
            ],
        ]);
    }

    /**
     * @param User         $student
     * @param Folder\Model $course
     */
    public function course(User $student, Folder\Model $course)
    {
        $this->getResponse()->content->set([
            'format'   => 'html',
            'template' => '@customMoriahCohort/course.twig',
            'data'     => [
                'student' => $student,
                'course'  => $course,
            ],
        ]);
    }
}
