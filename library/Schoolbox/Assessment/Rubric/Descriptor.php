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

/**
 * Class Descriptor
 *
 * @deprecated use `\Schoolbox\Assessment\Rubric\Grid\Descriptor` instead
 */
class Descriptor implements Calculable, Twiggable
{

    /** @var int */
    protected $id;

    /** @var string */
    protected $text;

    /**
     * The value of the descriptor, as set in the rubric grid.
     *
     * @var float
     */
    protected $value;

    /**
     * The actual selected value value of the descriptor, as set when marking
     * the rubric.
     *
     * As of 18.1 this may be different from `$this->value`.
     *
     * @var null|float
     */
    protected $selectedValue;

    /**
     * Is this descriptor checked? (to indicate it represents the student's attainment for the criterion)
     *
     * @var bool
     */
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
     * @param float  $selectedValue
     */
    public function __construct($text = '', $value = null, $id = null, $selected = false, $selectedValue = null)
    {
//        trigger_error(
//            'This class is deprecated; use "\Schoolbox\Assessment\Rubric\Grid\Descriptor" instead',
//            E_USER_DEPRECATED
//        );
        $this->id            = $id;
        $this->text          = $text;
        $this->value         = $value;
        $this->selected      = $selected;
        $this->selectedValue = $selectedValue;
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
     * @return float|null
     */
    public function getSelectedValue(): ?float
    {
        return $this->selectedValue;
    }


    /**
     * @param float|null $selectedValue
     * @return static
     */
    public function setSelectedValue(?float $selectedValue): self
    {
        $this->selectedValue = $selectedValue;
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
     * @inheritdoc
     *
     * @return float|null
     */
    public function calculateMark()
    {
        return $this->isSelected() ? $this->selectedValue : null;
    }


    /**
     * @inheritdoc
     *
     * @alias getValue()
     * @return float
     */
    public function calculateMax()
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
            'id'       => $this->getId(),
            'text'     => $this->getText(),
            'value'    => $this->getValue(),
            'selectedValue' => $this->getSelectedValue(),
            'selected' => $this->isSelected(),
            'sum'      => $this->calculateMark(),
            'total'    => $this->calculateMax(),
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
    public function setPosition(Level $level, Criterion $criterion)
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
