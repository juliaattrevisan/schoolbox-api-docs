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
        $this->getResponse()->content->set(
            array(
                'template' => '@customMeridenPastoral/index.twig',
                'data'     => $this->buildReportData($year, $student),
            )
        );
    }

    /**
     * Meriden's subject page
     *
     * @param int    $year
     * @param User   $student
     * @param string $subjectCode
     */
    public function subject($year, User $student, $subjectCode)
    {
        $this->getResponse()->content->set(
            array(
                'template' => '@customMeridenPastoral/subject.twig',
                'data'     => $this->buildReportData($year, $student, $subjectCode),
            )
        );
    }

    /**
     * Builds data for the report for this student for this year, with an
     * optional subject to drill down into.
     *
     * @param int    $year
     * @param User   $student
     * @param string $subjectCode
     *
     * @return array
     */
    protected function buildReportData($year, User $student, $subjectCode = null)
    {
        $yearLevelReport = array();
        $subjectReport   = array();
        if (!isset($subjectCode)) {
            // Only required for the eReport
            $yearLevelReport = array(
                'attendance' => $this->getDatabase()->getAllAttendances($year, $student),
                'activities' => $this->getDatabase()->getAllActivities($year, $student),
                'tutors'     => $this->getDatabase()->getAllTutors($year, $student),
            );
        } else {
            // Only required for the subject page
            list(, $code, $class) = explode('-', $subjectCode);
            $subjectReport = array(
                'subject' => $this->getDatabase()->getSubject($code),
            );
        }

        // Used in all reports, for the subject/year selector
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
        return array(
            'report' => array(
                'year'     => array(
                    'current'  => $year,
                    'previous' => $previousYear,
                    'next'     => $nextYear,
                    'levels'   => $yearLevels,
                    'subjects' => $subjects,
                ),
                'subject' => array(
                    'current' => $subjectCode,
                ),
                'student'  => $student,
                'yearLevelReport' => $yearLevelReport,
                'subjectReport' => $subjectReport,
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
