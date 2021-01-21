<?php
/**
 * Representation of a subject within Meriden.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement;

use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\User;

class Subject extends Involvement
{
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
     * @return IFeedback|null
     */
    public function retrieveLateralLearningFeedback(): ?IFeedback
    {
        $mostRecentFeedback = null;
        foreach ($this->iterateLateralLearningMarks() as $feedback) {
            if (!$mostRecentFeedback || $mostRecentFeedback->getPublishedAt()->lt($feedback->getPublishedAt())) {
                $mostRecentFeedback = $feedback;
            }
        }
        return $mostRecentFeedback;
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
                if (preg_match(self::LATERAL_LEARNING_CAPABILITY_REGEX, $capabilityUsage->getCapability()->getName())) {
                    yield $rubric->getFeedback();
                    break;
                }
            }
        }
    }
}
