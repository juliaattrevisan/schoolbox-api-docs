<?php
/**
 * Position
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\External\Normalise\User\Staff;


class Position {

    /**
     * Job position description
     *
     * @var string
     */
    private $description = '';

    /**
     * Position start date
     *
     * @var \Alaress_Date_Format
     */
    private $startDate = null;

    /**
     * Position end date
     *
     * @var \Alaress_Date_Format
     */
    private $endDate = null;

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
        $this->setStartDate(new \Alaress_Date_Format(null, 'U'));
        $this->setEndDate(new \Alaress_Date_Format(null, 'U'));
    }

    /* Getters/Setters
     */

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

    /**
     * Set end date
     *
     * @param \Alaress_Date_Format $endDate
     * @return $this
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * Get end date
     *
     * @return \Alaress_Date_Format
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Set start date
     *
     * @param \Alaress_Date_Format $startDate
     * @return $this
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * Get start date
     *
     * @return \Alaress_Date_Format
     */
    public function getStartDate()
    {
        return $this->startDate;
    }
}