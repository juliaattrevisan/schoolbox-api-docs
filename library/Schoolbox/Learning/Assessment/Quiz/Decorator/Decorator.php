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
     * @param $durationHours
     * @param $durationMinutes
     */
    public function __construct($durationHours, $durationMinutes)
    {
        $this->setDurationHours($durationHours);
        $this->setDurationMinutes($durationMinutes);
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
    }
}