<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent\Decorator;

use Schoolbox\Learning\Assessment\DecoratorInterface;
use Schoolbox\Learning\Assessment\DialogAgent\Model;

/**
 * Class Decorator
 *
 * @package Schoolbox\Learning\Assessment\DialogAgent\Decorator
 */
class Decorator implements DecoratorInterface
{
    use DialogAgentTrait;


    /**
     * Decorator constructor.
     */
    public function __construct(
        private int $scenarioId,
        private int $durationHours,
        private int $durationMinutes,
    ) {
    }


    /**
     * @param Model $assessment
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function decorate($assessment): void
    {
        // If hours or minutes field is empty, treat it as 0
        $durationHours = empty($this->getDurationHours()) ? 0 : $this->getDurationHours();
        $durationMinutes = empty($this->getDurationMinutes()) ? 0 : $this->getDurationMinutes();

        $duration = $this->convertToSeconds($durationHours, $durationMinutes);

        $assessment->setScenarioId($this->scenarioId);
        $assessment->setDuration($duration);
    }


    /**
     * Calculates $hours + $minutes into seconds
     *
     * TODO Technical Uplift Candidate:
     *  Note that this duped QuizDecorator. This is the second time it's been in use, so the next time we do this,
     *  it's probably worth splitting out.
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
            return Model::DURATION_UNLIMITED;
        }

        return (3600 * $hours) + (60 * $minutes);
    }
}
