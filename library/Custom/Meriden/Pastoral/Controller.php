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
        $tutorGroup    = null;
        $subject       = null;
        if (!isset($subjectCode)) {
            $tutorGroup = $this->getStudentInvolvementFactory()->buildTutorGroup($year, $student);
        } else {
            $subject = $this->getStudentInvolvementFactory()->buildSubject($year, $student, $subjectCode);
        }
        return array(
            'report' => array(
                'student'          => $student,
                'tutorGroupReport' => $tutorGroup,
                'subjectReport'    => $subject,
                'tutorGroupList'   => $this->getStudentInvolvementFactory()->buildTutorGroupList($year, $student),
                'subjectList'      => $this->getStudentInvolvementFactory()->buildSubjectList($year, $student),
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
