<?php
/**
 * QuizTrait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\Quiz\Decorator;

use Schoolbox\Learning\Quiz\Model as Quiz;

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

    /**
     * @var int
     */
    protected $markVisibility = Quiz::MARK_VISIBILITY_PRIVATE;

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
     * @return int
     */
    public function getMarkVisibility()
    {
        return $this->markVisibility;
    }

    /**
     * @param int $markVisibility
     * @return static
     */
    public function setMarkVisibility($markVisibility)
    {
        $this->markVisibility = $markVisibility;
        return $this;
    }
}
