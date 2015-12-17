<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;


class Descriptor implements Calculable, Twiggable
{

    /** @var string */
    protected $text;

    /** @var float */
    protected $value;

    /** @var bool */
    protected $selected;


    /**
     * @param string $text
     * @param float  $value
     * @param bool   $selected
     */
    public function __construct($text = '', $value = null, $selected = false)
    {
        $this->setText($text);
        $this->setValue($value);
        $this->setSelected($selected);
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
        ];
    }


}
