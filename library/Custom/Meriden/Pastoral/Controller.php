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
     * @var Factory
     */
    protected $studentInvolvementFactory;

    /**
     * Constructor
     *
     * @param Factory $studentInvolvementFactory
     */
    public function __construct(Factory $studentInvolvementFactory)
    {
        $this->setStudentInvolvementFactory($studentInvolvementFactory);
    }

    /**
     * Redirect to most recent report for this student.
     *
     * @param User $student
     */
    public function currentReport(User $student)
    {
        $currentYear = date('Y');
        $reportYears = array_map(
            function ($v) {
                return $v->getYear();
            },
            $this->getStudentInvolvementFactory()->buildTutorGroupList($student)
        );

        if (empty($reportYears)) {
            // 404
            $this->getResponse()->status->setCode(404);
        } else {
            // Redirect to current year, or last year in report
            $lastReportYear = max($reportYears);
            $this->getResponse()->redirect->found(
                $this->getRouter()->generate(
                    'customMeridenPastoral.index',
                    array(
                        'id'   => $student->getId(),
                        'year' => min($lastReportYear, $currentYear)
                    )
                )

            );
        }
    }

    /**
     * Index for Meriden's pastoral page
     *
     * @param int  $year
     * @param User $student
     */
    public function index($year, User $student)
    {
        try {
            $this->getResponse()->content->set(
                array(
                    'template' => '@customMeridenPastoral/index.twig',
                    'data'     => $this->buildReportData($year, $student),
                    'title'    => 'School Report - ' . $year . ' - ' . $student->formatName()
                )
            );
        } catch (\RangeException $e) {
            $this->getResponse()->status->setCode(404);
        }
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
        try {
            $reportData    = $this->buildReportData($year, $student, $subjectCode);
            /** @var StudentInvolvement\Subject $subjectReport */
            $subjectReport = $reportData['report']['subjectReport'];
            $this->getResponse()->content->set(
                array(
                    'template' => '@customMeridenPastoral/subject.twig',
                    'data'     => $reportData,
                    'title'    => $subjectReport->getName() . ' - ' . $year . ' - ' . $student->formatName()
                )
            );
        } catch (\RangeException $e) {
            $this->getResponse()->status->setCode(404);
        }
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
     *
     * @throws \RangeException if student isn't in year or subject
     */
    protected function buildReportData($year, User $student, $subjectCode = null)
    {
        // Reports are only for students who started year 7 in 2015 or later for now.
        $thisYear  = date('Y');
        $startYear = $thisYear + 7 - $student->getYear();
        if ($startYear < 2015) {
            throw new \RangeException("Reports are only for students who started year 7 in 2015 or later for now");
        }
        // Reports also aren't shown for year 10+.
        // What year would the student be in now?
        $reportYearLevel = $student->getYear() - ($thisYear - $year);
        if ($reportYearLevel >= 10) {
            throw new \RangeException("Reports are only for years 7, 8 and 9 for now");
        }
        $tutorGroup     = null;
        $tutorGroupList = $this->getStudentInvolvementFactory()->buildTutorGroupList($student);
        $subject        = null;
        $subjectList    = $this->getStudentInvolvementFactory()->buildSubjectList($year, $student);
        if (!isset($subjectCode)) {
            // Does this student exist for this year and subject?
            $exists = false;
            foreach ($tutorGroupList as $tutorGroup) {
                if ($tutorGroup->getYear() == $year) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                throw new \RangeException("Student has no tutor group in $year");
            }
            $tutorGroup = $this->getStudentInvolvementFactory()->buildTutorGroup($year, $student);
        } else {
            // Does this subject exist for this student?
            $exists = false;
            foreach ($subjectList as $subject) {
                if ($subject->getSubjectCode() == $subjectCode) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                throw new \RangeException("Student was not in subject $subjectCode in $year");
            }
            $subject = $this->getStudentInvolvementFactory()->buildSubject($year, $student, $subjectCode);
        }
        return array(
            'report' => array(
                'student'          => $student,
                'tutorGroupReport' => $tutorGroup,
                'subjectReport'    => $subject,
                'tutorGroupList'   => $tutorGroupList,
                'subjectList'      => $subjectList,
            )
        );
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
