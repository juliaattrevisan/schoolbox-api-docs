<?php

/**
 * Decorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Decorator;

use Assess_Main;
use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\Quiz\Model as Quiz;

class Decorator implements DecoratorInterface
{
    use QuizTrait;

    /**
     * Decorator constructor.
     */
    public function __construct(
        private string $learnosityReference,
        protected int $durationHours,
        protected int $durationMinutes,
        protected int $readingTimeDurationHours,
        protected int $readingTimeDurationMinutes,
        protected int $shuffleItems,
        protected int $maxAttempts,
        protected string $completionMessage
    ) {
    }

    /**
     * @param Quiz $assessment
     */
    public function decorate($assessment): void
    {
        // If hours or minutes field is empty, treat it as 0
        $durationHours = empty($this->getDurationHours()) ? 0 : $this->getDurationHours();
        $durationMinutes = empty($this->getDurationMinutes()) ? 0 : $this->getDurationMinutes();

        $duration = $this->convertToSeconds($durationHours, $durationMinutes);

        // If hours or minutes field is empty, treat it as 0
        $readingTimeDurationHours = empty($this->getReadingTimeDurationHours()) ? 0 : $this->getReadingTimeDurationHours();
        $readingTimeDurationMinutes = empty($this->getReadingTimeDurationMinutes()) ? 0 : $this->getReadingTimeDurationMinutes();

        $readingTimeDuration = $this->convertToSeconds($readingTimeDurationHours, $readingTimeDurationMinutes);

        $assessment->setDuration($duration);
        $assessment->setReadingTimeDuration($readingTimeDuration);
        $assessment->setShuffleItems($this->getShuffleItems());
        $assessment->setMaxAttempts($this->getMaxAttempts());
        $assessment->setCompletionMessage($this->getCompletionMessage());
        $assessment->setLearnosityReference($this->getLearnosityReference());
    }


    /**
     * Calculates $hours + $minutes into seconds
     *
     * @param int $hours
     * @param int $minutes
     * @return int
     */
    private function convertToSeconds(int $hours, int $minutes): int
    {
        /* Use unlimited duration if a specific duration isn't provided
         */
        if (!$hours && !$minutes) {
            return Assess_Main::UNLIMITED_DURATION;
        }

        return (3600 * $hours) + (60 * $minutes);
    }
}
