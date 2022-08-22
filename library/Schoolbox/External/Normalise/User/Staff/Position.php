<?php

/**
 * Position
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Staff;

use Schoolbox\Collection;

class Position
{

    /**
     * Job position descriptions
     * This is usually a nice human-readable format of a person's position
     * e.g. "Human Resources"
     *
     * @var Collection
     */
    private Collection $description;


    /**
     * Job position codes
     * This is some abbreviated format of a person's position
     * e.g. "HR", which is the short format of "Human Resources"
     *
     * @var Collection
     */
    private Collection $code;


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
     * @return Collection
     */
    public function getCode(): Collection
    {
        return $this->code;
    }


    /**
     * @param Collection $code
     * @return $this
     */
    public function setCode(Collection $code): self
    {
        $this->code = $code;
        return $this;
    }


    /**
     * Set descriptions
     *
     * @param Collection $description
     * @return $this
     */
    public function setDescription(Collection $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get descriptions
     *
     * @return Collection
     */
    public function getDescription(): Collection
    {
        return $this->description;
    }

    /**
     * Set end date
     *
     * @param \Alaress_Date_Format $endDate
     * @return $this
     */
    public function setEndDate($endDate): self
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
    public function setStartDate($startDate): self
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
     * @deprecated
     */
    public function __toString(): string
    {
        return implode(', ', $this->description->getAll());
    }
}
