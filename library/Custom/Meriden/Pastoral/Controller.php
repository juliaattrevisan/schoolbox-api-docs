<?php
/**
 * Controller for Meriden's pastoral page.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Meriden\Pastoral;

use Alaress\Web\Controller\Base;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * @var Query
     */
    protected $database;

    public function __construct(Query $database)
    {
        $this->setDatabase($database);
    }

    /**
     * Index for Meriden's pastoral page
     *
     * @param int  $year
     * @param User $student
     */
    public function index($year, User $student)
    {
        $attendance = $this->getDatabase()->getAllAttendances($year, $student);
        $activities = $this->getDatabase()->getAllActivities($year, $student);
        $tutors     = $this->getDatabase()->getAllTutors($year, $student);
        $yearLevels = $this->getDatabase()->getAllYearLevelGroups($student);
        $subjects   = $this->getDatabase()->augmentSubjectsWithTeacher(
            $this->getDatabase()->getAllSubjectsForYear($year, $student)
        );

        // Check previous and next year, disable if no year level group exists
        $previousYear = false;
        $nextYear     = false;
        foreach ($yearLevels as $level) {
            $yearLevel = $level['yr_study'];
            if ($yearLevel == ($year-1)) {
                $previousYear = $yearLevel;
            }
            if ($yearLevel == ($year+1)) {
                $nextYear = $yearLevel;
            }
        }

        $this->getResponse()->content->set(
            array(
                'template' => '@customMeridenPastoral/index.twig',
                'data'     => array(
                    'report' => array(
                        'year'     => array(
                            'current'  => $year,
                            'previous' => $previousYear,
                            'next'     => $nextYear,
                            'levels'   => $yearLevels,
                            'subjects' => $subjects,
                        ),
                        'student'  => $student,
                        'studentExternal' => array(
                            'attendance' => $attendance,
                            'activities' => $activities,
                            'tutors'     => $tutors,
                        ),
                    )
                ),
            )
        );
    }

    /**
     * Meriden's subject page
     *
     * @param int    $year
     * @param User   $student
     * @param string $subject
     */
    public function subject($year, User $student, $subject)
    {

        $this->getResponse()->content->set(
            array(
                'template' => '@customMeridenPastoral/subject.twig',
                'data'     => array(),
            )
        );
    }

    /**
     * @return Query
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param  Query $database
     *
     * @return $this
     */
    public function setDatabase($database)
    {
        $this->database = $database;
        return $this;
    }
}
