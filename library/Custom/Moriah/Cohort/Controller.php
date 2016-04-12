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
     * Action for displaying a list of classes for a student.
     *
     * @param User $student
     */
    public function classList(User $student)
    {
        $this->assertIsStudent($student);
        $classes = $this->folderMapper->findForUser($student);
        // Sort by name
        $classes->sortByCallback(
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
        // Load all courses associated with these classes
        $courses = $this->unitInstanceMapper->findAllCoursesUsedInFolders($classes->getColumnValues('id'));
        $this->getResponse()->content->set([
            'format'   => 'html',
            'template' => '@customMoriahCohort/classList.twig',
            'data'     => [
                'student' => $student,
                'classes' => $classes,
                'courses' => $courses,
            ],
        ]);
    }

    /**
     * Action for displaying a single class, with cohort reporting.
     *
     * @param User         $student
     * @param Folder\Model $class
     */
    public function classReport(User $student, Folder\Model $class)
    {
        $this->assertIsStudent($student);
        $this->getResponse()->content->set([
            'format'   => 'html',
            'template' => '@customMoriahCohort/classReport.twig',
            'data'     => [
                'student' => $student,
                'class'   => $class,
            ],
        ]);
    }

    /**
     * Asserts whether this user is even a student.
     *
     * @param  User $user
     * @throws \RangeException if they aren't.
     */
    protected function assertIsStudent(User $user)
    {
        if (!$user->getRole()->isStudent()) {
            throw new \RangeException('This report is only for students');
        }
    }
}
