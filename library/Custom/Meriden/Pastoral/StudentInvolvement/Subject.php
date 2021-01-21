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
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\User;

class Subject extends Involvement
{
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
     * Is this subject a Lateral Learning report?
     *
     * If the student has been marked for at least one lateral learning
     * assessment, then it is a lateral learning report.
     *
     * @return bool
     */
    public function isLateralLearning(): bool
    {
        foreach ($this->iterateLateralLearningMarks() as $feedback) {
            return true;
        }
        return false;
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
        foreach ($this->iterateLateralLearningMarks() as $feedback) {
            if (!$mostRecentFeedback || $mostRecentFeedback->getPublishedAt()->lt($feedback->getPublishedAt())) {
                $mostRecentFeedback = $feedback;
            }
        }
        return $mostRecentFeedback;
    }


    public function retrieveLateralLearningCapabilityMark(): ?CapabilityMark
    {
        $feedback = $this->retrieveLateralLearningFeedback();
        if ($feedback) {
            foreach ($feedback->getRubricMarkedGrid()->getCapabilityMarks() as $capabilityMark) {
                if ($this->isCapabilityLateralLearning($capabilityMark->getCapabilityUsage()->getCapability())) {
                    // do something
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
    private function iterateLateralLearningMarks(): iterable
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


    private function isCapabilityLateralLearning(Capability $capability): bool
    {
        return preg_match(self::LATERAL_LEARNING_CAPABILITY_REGEX, $capability->getName());
    }
}
