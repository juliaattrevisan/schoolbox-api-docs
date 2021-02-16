<?php

/**
 * Controller for Meriden's pastoral page.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Meriden\Pastoral;

use Alaress\Web\Controller\Base;
use Custom\Meriden\Pastoral\StudentInvolvement\Factory;
use Custom\Meriden\Pastoral\StudentInvolvement\Subject;
use Custom\Meriden\Pastoral\StudentInvolvement\TutorGroup;
use RangeException;
use Schoolbox\Role;
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
        $reportYears = $this->retrieveValidYearsForReport($student);

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
        } catch (RangeException $e) {
            $this->getResponse()->status->setCode(404);
        }
    }


    /**
     * Output a printable version of the report for this student.
     *
     * @api   GET /modules/custom/meriden/pastoral/{studentId}/print/{year}
     * @since 20.1.6
     *
     * @param int  $year
     * @param User $student
     */
    public function printableReport(int $year, User $student): void
    {
        // Figure out the data for the base report
        try {
            $baseReportData = $this->buildReportData($year, $student);
        } catch (RangeException $e) {
            $this->response->status->setCode(404);
            return;
        }

        $subjectReportDataList = [];
        foreach ($baseReportData['report']['subjectList'] as $subject) {
            /** @var Subject $subject */
            try {
                $subjectReportDataList[$subject->getSubjectCode()] = $this->buildReportData(
                    $year,
                    $student,
                    $subject->getSubjectCode()
                );
            } catch (RangeException $e) {
                // Skip this one
                continue;
            }
        }

        $this->response->content->set(
            $this->buildHtmlOutput(
                '@customMeridenPastoral/printableReport.twig',
                [
                    'baseReport'     => $baseReportData,
                    'subjectReports' => $subjectReportDataList,
                ],
                'School Report - ' . $year . ' - ' . $student->formatName()
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
        try {
            $reportData = $this->buildReportData($year, $student, $subjectCode);
            /** @var StudentInvolvement\Subject $subjectReport */
            $subjectReport = $reportData['report']['subjectReport'];
            $this->getResponse()->content->set(
                [
                    'template' => $subjectReport->isLateralLearning()
                        ? '@customMeridenPastoral/lateralLearning.twig'
                        : '@customMeridenPastoral/subject.twig',
                    'data'     => $reportData,
                    'title'    => $subjectReport->getName() . ' - ' . $year . ' - ' . $student->formatName(),
                ]
            );
        } catch (RangeException $e) {
            $this->getResponse()->status->setCode(404);
        }
    }

    /**
     * Builds data for the report for this student for this year, with an
     * optional subject to drill down into.
     *
     * @param int         $year
     * @param User        $student
     * @param string|null $subjectCode
     *
     * @return array
     *
     * @throws RangeException if student isn't in year or subject
     */
    protected function buildReportData($year, User $student, $subjectCode = null)
    {
        $this->assertValidYearForReport($year, $student);

        $tutorGroup       = null;
        $tutorGroupList   = $this->retrieveTutorGroupList($student);
        $subject          = null;
        $subjectList      = $this->getStudentInvolvementFactory()->buildSubjectList($year, $student);
        $roleTypeOverride = null;
        $roleTypes        = Role::getRoleTypes();

        // Only staff may override role type?
        if ($this->getUser()->getRole()->isStaff()) {
            $roleTypeOverride = $this->request->query->get('role-type');
            if (isset($roleTypeOverride) && !in_array($roleTypeOverride, $roleTypes, true)) {
                throw new RangeException(
                    sprintf("If role type is overridden, it must be one of: %s", implode(', ', $roleTypes))
                );
            }
        }

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
                throw new RangeException("Student has no tutor group in $year");
            }
            $tutorGroup = $this->getStudentInvolvementFactory()->buildTutorGroup($year, $student, $roleTypeOverride);
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
                throw new RangeException("Student was not in subject $subjectCode in $year");
            }

            if ($subject->isLateralLearning()) {
                $subject = $this->studentInvolvementFactory->buildLateralLearning(
                    $year,
                    $student,
                    $subjectCode,
                    $subjectList,
                    $roleTypeOverride
                );
            } else {
                $subject = $this->getStudentInvolvementFactory()->buildSubject(
                    $year,
                    $student,
                    $subjectCode,
                    $roleTypeOverride
                );
            }
        }
        return [
            'report' => [
                'year'             => $year,
                'student'          => $student,
                'tutorGroupReport' => $tutorGroup,
                'subjectReport'    => $subject,
                'tutorGroupList'   => $tutorGroupList,
                'subjectList'      => $subjectList,
                'roleTypeOverride' => $roleTypeOverride,
            ],
            'graphWidth' => $this->request->query->getInt('graph-width'),
        ];
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

    /**
     * Builds a list of tutor groups for this student, excluding invalid years.
     *
     * @param User $student
     * @return array|TutorGroup[]
     */
    private function retrieveTutorGroupList(User $student): array
    {
        return array_filter(
            $this->getStudentInvolvementFactory()->buildTutorGroupList($student),
            function (TutorGroup $tutorGroup) use ($student) {
                try {
                    $this->assertValidYearForReport($tutorGroup->getYear(), $student);
                    return true;
                } catch (RangeException $e) {
                    return false;
                }
            }
        );
    }

    /**
     * Retrieves a list of valid years for this student's report.
     *
     * @param User $student
     * @return array
     */
    private function retrieveValidYearsForReport(User $student): array
    {
        return array_map(
            function (TutorGroup $tutorGroup) {
                return $tutorGroup->getYear();
            },
            $this->retrieveTutorGroupList($student)
        );
    }

    /**
     * Asserts that this year is valid for the eReport.
     *
     * * Reports are only for students who started year 7 in 2015+ for now
     * * Reports are only for years the student is/was in year 7, 8 or 9
     *
     * @param int  $year
     * @param User $student
     * @throws RangeException if it's not valid
     */
    private function assertValidYearForReport($year, User $student): void
    {
        $thisYear  = (int)date('Y');
        $startYear = $thisYear + 7 - (int)$student->getYear()->getName();
        if ($startYear < 2015) {
            throw new RangeException("Reports are only for students who started year 7 in 2015 or later for now");
        }

        $reportYearLevel = ((int)$student->getYear()->getName()) - ($thisYear - $year);
        if ($reportYearLevel >= 10) {
            throw new RangeException("Reports are only for years 7, 8 and 9 for now");
        }
    }
}
