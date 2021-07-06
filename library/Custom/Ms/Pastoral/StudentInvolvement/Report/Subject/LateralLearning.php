<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Ms\Pastoral\StudentInvolvement\Report\Subject;

use Schoolbox\Assessment\Rubric\MarkedGrid\IndicatorMark;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet;

/**
 * Representation of report data for a lateral learning subject.
 */
class LateralLearning extends Base
{
    /**
     * Regular expression for lateral learning capabilities: used to identify if
     * an assessment's rubric should be on this report.
     */
    private const LATERAL_LEARNING_CAPABILITY_REGEX = '/Lateral\\s+Learning\\s+Skills/i';

    /**
     * The most recent mark given for each indicator.
     *
     * @var array|IndicatorMark[]
     */
    private array $indicatorMarks = [];

    /**
     * Collate the indicator marks from all grades given to the student, and
     * injects them into `$this->indicatorMarks`.
     *
     * @param array $allGrades All grades given to this student across all
     *                         subjects in this eReport.
     *
     * @return $this
     */
    public function injectIndicatorMarks(array $allGrades): self
    {
        $indicatorMarksByName = [];
        foreach ($allGrades as $grade) {
            /** @var MarkedGrid|null $rubric */
            $rubric = $grade['rubric'];
            if ($rubric) {
                foreach ($rubric->getCapabilityMarks() as $capabilityMark) {
                    // Does this assessment have a rubric, and does that rubric
                    // have the lateral learning capability?
                    // If so: store the indicator mark and feedback it's part of
                    if ($this->isCapabilityLateralLearning($capabilityMark->getCapabilityUsage()->getCapability())) {
                        $feedback = $rubric->getFeedback();
                        foreach ($capabilityMark->getIndicatorMarks() as $indicatorMark) {
                            $indicatorMarksByName[$indicatorMark->getIndicatorUsage()->getIndicator()->getName()][] = [
                                'feedback' => $feedback,
                                'mark'     => $indicatorMark,
                            ];
                        }
                    }
                }
            }
        }

        // Find the most recent mark for each indicator
        $mostRecentIndicatorMarks = [];
        foreach ($indicatorMarksByName as $name => $indicatorMarks) {
            $mostRecentTimestamp = null;
            foreach ($indicatorMarks as $indicatorMark) {
                $timestamp = $indicatorMark['feedback']->getPublishedAt()->getUnixTimeStamp();
                if (!isset($mostRecentTimestamp) || $mostRecentTimestamp < $timestamp) {
                    $mostRecentTimestamp             = $timestamp;
                    $mostRecentIndicatorMarks[$name] = $indicatorMark['mark'];
                }
            }
        }

        // Sort the list by natcase for output
        uksort($mostRecentIndicatorMarks, 'strnatcasecmp');
        $this->indicatorMarks = $mostRecentIndicatorMarks;

        return $this;
    }

    /**
     * @return array|IndicatorMark[]
     */
    public function getIndicatorMarks(): array
    {
        return $this->indicatorMarks;
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
