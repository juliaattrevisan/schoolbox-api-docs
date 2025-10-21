<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DialogAgent\Decorator;

trait DialogAgentTrait
{
    /**
     * @var int
     */
    private int $durationHours;


    /**
     * @var int
     */
    private int $durationMinutes;


    /**
     * @var int
     */
    private int $scenarioId;


    /* Getters/Setters
     */

    /**
     * @return int
     */
    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }


    /**
     * @param int $durationMinutes
     * @return $this
     */
    public function setDurationMinutes(int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;
        return $this;
    }


    /**
     * @return int
     */
    public function getDurationHours(): int
    {
        return $this->durationHours;
    }


    /**
     * @param int $durationHours
     * @return $this
     */
    public function setDurationHours(int $durationHours): self
    {
        $this->durationHours = $durationHours;
        return $this;
    }


    /**
     * @return int
     */
    public function getScenarioId(): int
    {
        return $this->scenarioId;
    }


    /**
     * @param int $scenarioId
     * @return $this
     */
    public function setScenarioId(int $scenarioId): self
    {
        $this->scenarioId = $scenarioId;
        return $this;
    }
}
