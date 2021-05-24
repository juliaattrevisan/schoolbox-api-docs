<?php

/**
 * QuizTrait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Decorator;

trait QuizTrait
{

    /**
     * @var int
     */
    protected $durationHours = 0;

    /**
     * @var int
     */
    protected $durationMinutes = 0;

    /**
     * @var int
     */
    protected $maxAttempts = 0;

    /** @var string */
    protected $completionMessage;

    /**
     * @return int
     */
    public function getDurationHours()
    {
        return $this->durationHours;
    }

    /**
     * @param int $durationHours
     * @return $this
     */
    public function setDurationHours($durationHours)
    {
        $this->durationHours = $durationHours;
        return $this;
    }

    /**
     * @return int
     */
    public function getDurationMinutes()
    {
        return $this->durationMinutes;
    }

    /**
     * @param int $durationMinutes
     * @return $this
     */
    public function setDurationMinutes($durationMinutes)
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxAttempts()
    {
        return $this->maxAttempts;
    }

    /**
     * @param int $maxAttempts
     * @return static
     */
    public function setMaxAttempts($maxAttempts)
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * @return string
     */
    public function getCompletionMessage()
    {
        return $this->completionMessage;
    }

    /**
     * @param string $completionMessage
     * @return static
     */
    public function setCompletionMessage($completionMessage)
    {
        $this->completionMessage = $completionMessage;
        return $this;
    }
}
