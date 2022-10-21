<?php

/**
 * ProjectTrait
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Project\Decorator;

trait ProjectTrait
{
    /**
     * @var string
     */
    protected $summator;

    /**
     * Does this project require that all activities be attempted by a
     * participant?
     *
     * If so, activities which a participant hasn't attempted will be considered
     * as having a zero mark.
     */
    private bool $requireAllActivities;

    /**
     * @return string
     */
    protected function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param string $summator
     * @return $this
     */
    protected function setSummator($summator)
    {
        $this->summator = $summator;
        return $this;
    }

    /**
     * @return bool
     */
    protected function getRequireAllActivities(): bool
    {
        return $this->requireAllActivities;
    }

    /**
     * @param bool $requireAllActivities
     * @return $this
     */
    protected function setRequireAllActivities(bool $requireAllActivities)
    {
        $this->requireAllActivities = $requireAllActivities;
        return $this;
    }
}
