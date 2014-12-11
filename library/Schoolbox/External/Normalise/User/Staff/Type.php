<?php
/**
 * Type
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Staff;


class Type {

    /**
     * Category type (type of staff)
     *
     * @var string
     */
    private $type = '';

    /**
     * Department name
     *
     * @var string
     */
    private $department = '';

    /**
     * Job position
     *
     * @var Position
     */
    private $position = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup() {
        $this->setPosition(new Position());
    }

    /* Getters/Setters
     */

    /**
     * Set department
     *
     * @param string $department
     * @return $this
     */
    public function setDepartment($department)
    {
        $this->department = $department;
        return $this;
    }

    /**
     * Get department
     *
     * @return string
     */
    public function getDepartment()
    {
        return $this->department;
    }

    /**
     * Set position
     *
     * @param Position $position
     * @return $this
     */
    private function setPosition($position)
    {
        $this->position = $position;
        return $this;
    }

    /**
     * Get position
     *
     * @return Position
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return $this
     */
    private function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}