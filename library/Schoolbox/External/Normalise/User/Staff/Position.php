<?php

/**
 * Position
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Staff;

class Position
{

    /**
     * Job position description
     * This is usually a nice human-readable format of a person's position
     * e.g. "Human Resources"
     *
     * @var string
     */
    private string $description = '';


    /**
     * Job position code
     * This is some abbreviated format of a person's position
     * e.g. "HR", which is the short format of "Human Resources"
     *
     * @var string
     */
    private string $code = '';


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
    public function __construct()
    {
        $this->setup();
    }

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->setStartDate(new \Alaress_Date_Format(null, 'U'));
        $this->setEndDate(new \Alaress_Date_Format(null, 'U'));
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }


    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
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


    /**
     * This magic method shouldn't exist when meta.position token is removed
     *
     * It bandaided over a wonky implementation of multiple position descriptions
     * and shouldn't really be here
     *
     * @return string
     *@deprecated
     */
    public function __toString()
    {
        return $this->description ?: '';
    }
}
