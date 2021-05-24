<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Quiz\Section\Question\QuestionType;

use Schoolbox\Mapper\Traits\Model\Id;

/**
 * Teachers may create Questions of the these QuestionTypes
 */
class Model
{
    use Id;

    /**
     * @var string
     */
    private $className;

    /**
     * @var string
     */
    private $fileName;

    /**
     * @var string
     */
    private $description;

    /**
     * @var string
     */
    private $moodle;

    /**
     * @var bool
     */
    private $automatedMarking;

    /**
     * @var string
     */
    private $availability;

    /**
     * @var bool
     */
    private $hasLegend;

    /**
     * @var bool
     */
    private $hasConfig;

    /* Getters / Setters
     */

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param string $className
     * @return static
     */
    public function setClassName($className)
    {
        $this->className = $className;
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @param string $fileName
     * @return static
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return static
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getMoodle()
    {
        return $this->moodle;
    }

    /**
     * @param string $moodle
     * @return static
     */
    public function setMoodle($moodle)
    {
        $this->moodle = $moodle;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isAutomatedMarking()
    {
        return $this->automatedMarking;
    }

    /**
     * @param boolean $automatedMarking
     * @return static
     */
    public function setAutomatedMarking($automatedMarking)
    {
        $this->automatedMarking = $automatedMarking;
        return $this;
    }

    /**
     * @return string
     */
    public function getAvailability()
    {
        return $this->availability;
    }

    /**
     * @param string $availability
     * @return static
     */
    public function setAvailability($availability)
    {
        $this->availability = $availability;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getHasLegend()
    {
        return $this->hasLegend;
    }

    /**
     * @param boolean $hasLegend
     * @return static
     */
    public function setHasLegend($hasLegend)
    {
        $this->hasLegend = $hasLegend;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getHasConfig()
    {
        return $this->hasConfig;
    }

    /**
     * @param boolean $hasConfig
     * @return static
     */
    public function setHasConfig($hasConfig)
    {
        $this->hasConfig = $hasConfig;
        return $this;
    }
}
