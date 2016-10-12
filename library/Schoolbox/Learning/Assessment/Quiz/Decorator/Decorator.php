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
     */
    public function __construct($durationHours, $durationMinutes, $maxAttempts)
    {
        $this->setDurationHours($durationHours);
        $this->setDurationMinutes($durationMinutes);
        $this->setMaxAttempts($maxAttempts);
    }

    /**
     * @param Quiz $assessment
     */
    public function decorate($assessment)
    {
        /* TODO: Work out $numSeconds from $this->getDurationHours() and $this->getDurationMinutes()
         */
        $duration = $this->getDurationHours() + $this->getDurationMinutes();

        $assessment->setDuration($duration);

        $assessment->setMaxAttempts($this->getMaxAttempts());
    }
}
