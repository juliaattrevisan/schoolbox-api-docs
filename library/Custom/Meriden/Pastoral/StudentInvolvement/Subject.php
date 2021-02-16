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
    private const LATERAL_LEARNING_CAPABILITY_REGEX = '/Lateral\\s+Learning\\s+Skills/i';

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
//        return (bool)preg_match(self::LATERAL_LEARNING_CLASS_NAME_REGEX, $this->folder->getName());
//        if ($this->rubricRowSet) {
//            foreach ($this->rubricRowSet->getCapabilities() as $capability) {
//                if ($this->isCapabilityLateralLearning($capability)) {
//                    return true;
//                }
//            }
//        }
//        return false;
    }

    /**
     * Retrieves the most recently given feedback for a lateral learning
     * assessment.
     *
     * @return Model|null
     */
    public function retrieveLateralLearningFeedback(): ?Model
    {
        $mostRecentFeedback = null;
        foreach ($this->iterateLateralLearningFeedback() as $feedback) {
            if (!$mostRecentFeedback || $mostRecentFeedback->getPublishedAt()->lt($feedback->getPublishedAt())) {
                $mostRecentFeedback = $feedback;
            }
        }
        return $mostRecentFeedback;
    }

    /**
     * Retrieves the lateral learning capability mark for the most recent
     * lateral learning feedback.
     *
     * The capability mark will contain the indicator marks which are displayed
     * on the lateral learning report.
     *
     * @return CapabilityMark|null
     */
    public function retrieveLateralLearningCapabilityMark(): ?CapabilityMark
    {
        $feedback = $this->retrieveLateralLearningFeedback();
        if ($feedback && $feedback->getRubricMarkedGrid()) {
            foreach ($feedback->getRubricMarkedGrid()->getCapabilityMarks() as $capabilityMark) {
                if ($this->isCapabilityLateralLearning($capabilityMark->getCapabilityUsage()->getCapability())) {
                    return $capabilityMark;
                }
            }
        }
        return null;
    }

    /**
     * Iterator for feedback from lateral learning assessments.
     *
     * @return iterable|IFeedback[]
     */
    private function iterateLateralLearningFeedback(): iterable
    {
        foreach ($this->getGrades() as $grade) {
            $rubric = $grade['rubric'] ?? null;
            if (!$rubric instanceof MarkedGrid) {
                continue;
            }
            foreach ($rubric->iterateCapabilityUsages() as $capabilityUsage) {
                if ($this->isCapabilityLateralLearning($capabilityUsage->getCapability())) {
                    yield $rubric->getFeedback();
                    break;
                }
            }
        }
    }

    /**
     * Does the given capability identify its container folder as a lateral
     * learning class?
     *
     * @param RowSet\Capability $capability
     * @return bool
     */
    private function isCapabilityLateralLearning(RowSet\Capability $capability): bool
    {
        return preg_match(self::LATERAL_LEARNING_CAPABILITY_REGEX, $capability->getName());
    }
}
