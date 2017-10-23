<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Personalised\Participant;

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use Schoolbox\Learning\Assessment\DataModel;
use Schoolbox\User;

/**
 * Representation for a personalised assessment participation for a student.
 *
 * Currently, it is not the source of truth for whether the student is actually
 * participating in an assessment - it only allows a student to override the
 * open and due date for an assessment.
 *
 * Eventually, it may be expanded to be the record for whether a student is
 * participating in an assessment.
 */
class Model
{
    /**
     * The assessment for which the date has been personalised.
     *
     * @var DataModel\Model
     */
    protected $assessment;

    /**
     * The student for which the date has been personalised.
     *
     * @var User
     */
    protected $student;

    /**
     * This student's custom open date for this assessment.
     *
     * @var Alaress_Date_Format
     */
    protected $openDate;

    /**
     * This student's custom due date for this assessment.
     *
     * @var Alaress_Date_Format
     */
    protected $dueDate;

    /**
     * @return DataModel\Model
     */
    public function getAssessment()
    {
        return $this->assessment;
    }

    /**
     * @param DataModel\Model $assessment
     * @return static
     */
    public function setAssessment(DataModel\Model $assessment)
    {
        $this->assessment = $assessment;
        return $this;
    }

    /**
     * @return User
     */
    public function getStudent()
    {
        return $this->student;
    }

    /**
     * @param User $student
     * @return static
     */
    public function setStudent(User $student)
    {
        $this->student = $student;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate()
    {
        return $this->openDate;
    }

    /**
     * @param Alaress_Date_Format $openDate
     * @return static
     */
    public function setOpenDate(Alaress_Date_Format $openDate = null)
    {
        $this->openDate = $openDate ?: new Alaress_Date_NullDate();
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * @param Alaress_Date_Format $dueDate
     * @return static
     */
    public function setDueDate(Alaress_Date_Format $dueDate = null)
    {
        $this->dueDate = $dueDate ?: new Alaress_Date_NullDate();
        return $this;
    }
}
