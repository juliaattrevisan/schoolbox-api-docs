<?php

/**
 * Year
 *
 * This happens to be synonymous with Year
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Staff;

class Year
{

    /**
     * The numeric value of a students' Year
     *
     * e.g. 4th Year = Year 10
     *
     * @var int
     */
    protected $level = -1;

    /**
     * Some kind of textual description
     *
     * @var string
     */
    protected $description = '';

    /* Getters/Setters
     */

    /**
     * Set level
     *
     * @param int $level
     * @return $this
     */
    public function setLevel($level)
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Get level
     *
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}
