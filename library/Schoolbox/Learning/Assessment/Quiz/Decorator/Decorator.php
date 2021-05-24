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
     * @param int $durationHours
     * @param int $durationMinutes
     * @param int $maxAttempts
     * @param string $completionMessage
     */
    public function __construct($durationHours, $durationMinutes, $maxAttempts, $completionMessage)
    {
        $this->setDurationHours($durationHours);
        $this->setDurationMinutes($durationMinutes);
        $this->setMaxAttempts($maxAttempts);
        $this->setCompletionMessage($completionMessage);
    }

    /**
     * @param Quiz $assessment
     */
    public function decorate($assessment)
    {
        // If hours or minutes field is empty, treat it as 0
        $durationHours = empty($this->getDurationHours()) ? 0 : $this->getDurationHours();
        $durationMinutes = empty($this->getDurationMinutes()) ? 0 : $this->getDurationMinutes();

        /* Use unlimited duration if a specific duration isn't provided
         */
        if (!$durationHours && !$durationMinutes) {
            $duration = Assess_Main::UNLIMITED_DURATION;
        } else {
            $duration = (3600 * $durationHours) + (60 * $durationMinutes);
        }

        $assessment->setDuration($duration);
        $assessment->setMaxAttempts($this->getMaxAttempts());
        $assessment->setCompletionMessage($this->getCompletionMessage());
    }
}
