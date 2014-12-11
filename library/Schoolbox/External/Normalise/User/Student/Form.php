<?php
/**
 * Form
 *
 * This happens to be synonymous with Year
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Student;


class Form {

    /**
     * The numeric value of a students' Form
     *
     * e.g. 4th Form = Year 10
     *
     * @var int
     */
    protected $level = -1;

    /**
     * Form teacher/Staff member in charge
     *
     * @var \Schoolbox\External\Normalise\User\Staff
     */
    protected $coordinator = null;

    /**
     * Constructor
     *
     * Immutable
     * 
     * @param $level
     * @param $coordinator
     */
    public function __construct($level, $coordinator) {
        $this->setLevel($level);
        $this->setCoordinator($coordinator);
    }

    /* Getters/Setters
     */

    /**
     * Set coordinator
     *
     * @param \Schoolbox\External\Normalise\User\Base $coordinator
     */
    protected function setCoordinator($coordinator)
    {
        $this->coordinator = $coordinator;
    }

    /**
     * Get coordinator
     *
     * @return \Schoolbox\External\Normalise\User\Base
     */
    public function getCoordinator()
    {
        return $this->coordinator;
    }

    /**
     * Set level
     *
     * @param int $level
     */
    protected function setLevel($level)
    {
        $this->level = $level;
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
}