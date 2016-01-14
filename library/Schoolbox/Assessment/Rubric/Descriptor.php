<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


use Schoolbox\Assessment\Rubric\Competency\Level;
use Schoolbox\Assessment\Rubric\Criteria\Criterion;

class Descriptor implements Calculable, Twiggable
{

    /** @var int */
    protected $id;

    /** @var string */
    protected $text;

    /** @var float */
    protected $value;

    /** @var bool */
    protected $selected;

    /** @var Criterion */
    protected $criterion;

    /** @var Level */
    protected $level;


    /**
     * @param string $text
     * @param float  $value
     * @param int    $id
     * @param bool   $selected
     */
    public function __construct($text = '', $value = null, $id = 0, $selected = false)
    {
        $this->setId($id);
        $this->setText($text);
        $this->setValue($value);
        $this->setSelected($selected);
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }


    /**
     * @return mixed
     */
    public function getText()
    {
        return $this->text;
    }


    /**
     * @param mixed $text
     * @return static
     */
    public function setText($text)
    {
        $this->text = $text;
        return $this;
    }


    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }


    /**
     * @param mixed $value
     * @return static
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }


    /**
     * @return boolean
     */
    public function isSelected()
    {
        return $this->selected;
    }


    /**
     * @param boolean $selected
     * @return static
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;
        return $this;
    }


    /**
     * Return the calculated sum value of this Calculable (NOT the total possible value)
     *
     * @return float
     */
    public function getSum()
    {
        return $this->isSelected() ? $this->value : 0;
    }


    /**
     * Returns the total possible value of this Calculable
     *
     * @alias getValue()
     * @return float
     */
    public function getTotal()
    {
        return $this->getValue();
    }


    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getText();
    }


    /**
     * Convert yourself into a Twig context array
     *
     * @return array
     */
    public function toContext()
    {
        return [
            'text'     => $this->getText(),
            'value'    => $this->getValue(),
            'selected' => $this->isSelected(),
            'sum'      => $this->getSum(),
            'total'    => $this->getTotal(),
            'position' => [
                'level' => [
                    'id' => $this->level->getId(),
                    'name' => $this->level->getName(),
                ],
                'criterion' => [
                    'id' => $this->criterion->getId(),
                    'name' => $this->criterion->getName(),
                ]
            ]
        ];
    }


    /**
     * @param Level $level
     * @param Criterion $criterion
     * @return self
     */
    public function setPosition($level, $criterion)
    {
        $this->level = $level;
        $this->criterion = $criterion;

        return $this;
    }


    /**
     * @return Criterion
     */
    public function getCriterion()
    {
        return $this->criterion;
    }


    /**
     * @return Level
     */
    public function getLevel()
    {
        return $this->level;
    }

}
