<?php
/**
 * Representation of a subject within Meriden.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\User;

class Subject extends Involvement
{
    /**
     * Regular expression for lateral learning class pages: used to identify if
     * this subject should display as a lateral learning report.
     */
    private const LATERAL_LEARNING_CLASS_NAME_REGEX = '/Lateral\\s+Learning/i';

    /**
     * Regular expression for lateral learning capabilities: used to identify if
     * this subject should display as a lateral learning report.
     */
    public const LATERAL_LEARNING_CAPABILITY_REGEX = '/Lateral\\s+Learning\\s+Skills/i';

    /**
     * External data (class name and description)
     *
     * @var array
     */
    protected $externalData;

    /**
     * The teachers for this subject
     *
     * @var array|User[]
     */
    protected $teachers;

    /**
     * This subject's rubric rowset, if it has one.
     */
    private ?RowSet\RowSet $rubricRowSet;

    /**
     * @return array
     */
    public function getExternalData()
    {
        return $this->externalData;
    }

    /**
     * @param  array $externalData
     *
     * @return $this
     */
    public function setExternalData($externalData)
    {
        $this->externalData = $externalData;
        return $this;
    }

    /**
     * @return array|\Schoolbox\User[]
     */
    public function getTeachers()
    {
        return $this->teachers;
    }

    /**
     * @param  array|\Schoolbox\User[] $teachers
     *
     * @return $this
     */
    public function setTeachers($teachers)
    {
        $this->teachers = $teachers;
        return $this;
    }

    /**
     * @return RowSet\RowSet|null
     */
    public function getRubricRowSet(): ?RowSet\RowSet
    {
        return $this->rubricRowSet;
    }

    /**
     * @param RowSet\RowSet|null $rubricRowSet
     */
    public function setRubricRowSet(?RowSet\RowSet $rubricRowSet): void
    {
        $this->rubricRowSet = $rubricRowSet;
    }

    /**
     * Is this subject a Lateral Learning report?
     *
     * If the subject's class page is named "Lateral Learning", then it's a
     * lateral learning report.
     *
     * @return bool
     */
    public function isLateralLearning(): bool
    {
        return (bool)preg_match(self::LATERAL_LEARNING_CLASS_NAME_REGEX, $this->getName());
    }
}
