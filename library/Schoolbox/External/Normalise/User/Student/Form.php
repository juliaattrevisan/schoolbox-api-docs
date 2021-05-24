<?php

/**
 * Form
 *
 * This happens to be synonymous with Year
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Student;

use Schoolbox\External\Normalise\User\Staff;

class Form
{

    /**
     * The numeric value of a students' Form
     *
     * e.g. 4th Form = Year 10
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

    /**
     * Form teacher/Staff member in charge
     *
     * @var Staff
     */
    protected $coordinator = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setCoordinator(new Staff());
    }

    /* Getters/Setters
     */

    /**
     * Set coordinator
     *
     * @param Staff $coordinator
     * @return $this
     */
    protected function setCoordinator($coordinator)
    {
        $this->coordinator = $coordinator;
        return $this;
    }

    /**
     * Get coordinator
     *
     * @return Staff
     */
    public function getCoordinator()
    {
        return $this->coordinator;
    }

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
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
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
