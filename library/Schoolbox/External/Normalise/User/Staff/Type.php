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
     *
     * Immutable
     *
     * @param $type
     * @param $department
     * @param $position
     */
    public function __construct($type, $department, $position) {
        $this->setType($type);
        $this->setDepartment($department);
        $this->setPosition($position);
    }

    /* Getters/Setters
     */

    /**
     * Set department
     *
     * @param string $department
     */
    private function setDepartment($department)
    {
        $this->department = $department;
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
     * @param \Schoolbox\External\Normalise\User\Staff\Position $position
     */
    private function setPosition($position)
    {
        $this->position = $position;
    }

    /**
     * @return \Schoolbox\External\Normalise\User\Staff\Position
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    private function setType($type)
    {
        $this->type = $type;
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