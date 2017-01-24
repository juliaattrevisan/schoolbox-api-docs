<?php
/**
 * Decorator
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\Assessment\Quiz\Decorator;

use Assess_Main;
use \Schoolbox\Learning\Assessment\DecoratorInterface;
use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;

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
        /* Use unlimited duration if a specific duration isn't provided
         */
        if (!$this->getDurationHours() && !$this->getDurationMinutes()) {
            $duration = Assess_Main::UNLIMITED_DURATION;

        } else {
            $duration = (3600 * $this->getDurationHours()) + (60 * $this->getDurationMinutes());
        }

        $assessment->setDuration($duration);
        $assessment->setMaxAttempts($this->getMaxAttempts());
        $assessment->setCompletionMessage($this->getCompletionMessage());
    }
}
