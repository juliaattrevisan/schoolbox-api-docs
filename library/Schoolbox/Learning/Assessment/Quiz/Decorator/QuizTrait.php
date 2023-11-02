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
    protected int $durationHours;

    /**
     * @var int
     */
    protected int $durationMinutes;

    /**
     * @var int
     */
    protected int $readingTimeDurationHours;

    /**
     * @var int
     */
    protected int $readingTimeDurationMinutes;

    /**
     * @var int
     */
    protected int $shuffleItems;

    /**
     * @var int
     */
    protected int $promptsMode;

    /**
     * @var int
     */
    protected int $maxAttempts;

    /** @var string */
    protected string $completionMessage;

    /**
     * @var string
     */
    private string $learnosityReference;

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
    public function getReadingTimeDurationHours()
    {
        return $this->readingTimeDurationHours;
    }

    /**
     * @param int $readingTimeDurationHours
     * @return $this
     */
    public function setReadingTimeDurationHours($readingTimeDurationHours)
    {
        $this->readingTimeDurationHours = $readingTimeDurationHours;
        return $this;
    }

    /**
     * @return int
     */
    public function getReadingTimeDurationMinutes()
    {
        return $this->readingTimeDurationMinutes;
    }

    /**
     * @param int $readingTimeDurationMinutes
     * @return $this
     */
    public function setReadingTimeDurationMinutes($readingTimeDurationMinutes)
    {
        $this->readingTimeDurationMinutes = $readingTimeDurationMinutes;
        return $this;
    }

    /**
     * @return int
     */
    public function getShuffleItems()
    {
        return $this->shuffleItems;
    }

    /**
     * @param int $shuffleItems
     * @return $this
     */
    public function setShuffleItems($shuffleItems)
    {
        $this->shuffleItems = $shuffleItems;
        return $this;
    }

    /**
     * @return int
     */
    public function getPromptsMode()
    {
        return $this->promptsMode;
    }

    /**
     * @param int $promptsMode
     * @return $this
     */
    public function setPromptsMode($promptsMode)
    {
        $this->promptsMode = $promptsMode;
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


    /**
     * @return string|null
     */
    public function getLearnosityReference(): ?string
    {
        return $this->learnosityReference;
    }


    /**
     * @param string|null $learnosityReference
     * @return static
     */
    public function setLearnosityReference(?string $learnosityReference)
    {
        $this->learnosityReference = $learnosityReference;
        return $this;
    }
}
