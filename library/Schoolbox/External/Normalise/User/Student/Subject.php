<?php

/**
 * Subject
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\External\Normalise\User\Student;

use Schoolbox\External\Normalise\User\Common\Campus;

class Subject
{

    /**
     * Abbreviated code
     *
     * @var string
     */
    private $code = '';

    /**
     * Name
     *
     * @var string
     */
    private $name = '';

    /**
     * Type/Category/Grouping title
     *
     * @var string
     */
    private $type = '';

    /**
     * Campus
     *
     * @var Campus
     */
    private $campus = null;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set campus
     *
     * @param \Schoolbox\External\Normalise\User\Common\Campus $campus
     * @return $this
     */
    public function setCampus($campus)
    {
        $this->campus = $campus;
        return $this;
    }

    /**
     * Get campus
     *
     * @return \Schoolbox\External\Normalise\User\Common\Campus
     */
    public function getCampus()
    {
        return $this->campus;
    }

    /**
     * Set code
     *
     * @param string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set type
     *
     * @param string $type
     * @return $this
     */
    public function setType($type)
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
