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
     *
     * Immutable
     *
     * @param $description
     * @param $startDate
     * @param $endDate
     */
    public function __construct($description, $startDate, $endDate) {
        $this->setDescription($description);
        $this->setStartDate($startDate);
        $this->setEndDate($endDate);
    }

    /* Getters/Setters
     */

    /**
     * Set description
     *
     * @param string $description
     */
    private function setDescription($description)
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

    /**
     * Set end date
     *
     * @param \Alaress_Date_Format $endDate
     */
    private function setEndDate($endDate)
    {
        $this->endDate = $endDate;
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
     */
    private function setStartDate($startDate)
    {
        $this->startDate = $startDate;
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