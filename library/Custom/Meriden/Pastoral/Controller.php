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
use Custom\Meriden\Pastoral\StudentInvolvement\Factory;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * @var Query
     */
    protected $database;

    /**
     * @var Factory
     */
    protected $studentInvolvementFactory;

    /**
     * Constructor
     *
     * @param Query   $database
     * @param Factory $studentInvolvementFactory
     */
    public function __construct(
        Query $database,
        Factory $studentInvolvementFactory
    ) {
        $this->setDatabase($database)->setStudentInvolvementFactory($studentInvolvementFactory);
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
        $subjectReport = array();
        $tutorGroup    = null;
        $subject       = null;
        if (!isset($subjectCode)) {
            // Only required for the eReport
            // Get more information about the tutor group too.
//            new \Alaress_Probe(
//                $this->getFactory()->build($year, $student)
//            );
            $tutorGroup = $this->getStudentInvolvementFactory()->buildTutorGroup($year, $student);
        } else {
            $subject = $this->getStudentInvolvementFactory()->buildSubject($year, $student, $subjectCode);
            // Only required for the subject page
//            $subjectReport = array(
//                'subject' => $this->getDatabase()->getSubject($subjectCode),
////                'subject' => $this->getDatabase()->getSubject($subjectCode, $student),
////                'folder'  => $this->getDatabase()->getFolder($subjectCode, $student),
//            );
        }

        // Used in all reports, for the subject/year selector
        $yearLevels = $this->getDatabase()->getAllYearLevelGroups($student);
        $subjects   = $this->getDatabase()->getAllSubjectsForYear($year, $student);

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
//                'subject' => array(
//                    'current' => $subjectCode,
//                ),
                'student'  => $student,
                'tutorGroup' => $tutorGroup,
                'subjectReport' => $subjectReport,
                'subject' => $subject,
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

    /**
     * @return Factory
     */
    public function getStudentInvolvementFactory()
    {
        return $this->studentInvolvementFactory;
    }

    /**
     * @param  Factory $studentInvolvementFactory
     *
     * @return $this
     */
    public function setStudentInvolvementFactory($studentInvolvementFactory)
    {
        $this->studentInvolvementFactory = $studentInvolvementFactory;
        return $this;
    }
}
