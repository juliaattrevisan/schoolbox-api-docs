<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_Result
 * @deprecated concept is entirely deprecated, no equivalent class exists
 */
class DueWork_Rubric_Result {

    private $label; // string
    private $value; // int


    /**
     * DueWork_Rubric_Result constructor.
     * @deprecated
     *
     * @param $label
     * @param $value
     */
    public function __construct($label, $value) {
        $this->label = $label;
        $this->value = $value;
    }

    public function setLabel($label) {
        $this->label = $label;
    }

    public function getLabel() {
        return $this->label;
    }

    public function setValue($value) {
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }


}
