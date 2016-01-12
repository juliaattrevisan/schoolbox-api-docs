<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_Descriptor
 * @deprecated use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Descriptor
 */
class DueWork_Rubric_Descriptor {

    private $description;   // string
    private $value;         // int


    /**
     * DueWork_Rubric_Descriptor constructor.
     * @deprecated
     *
     * @param $desc
     * @param $value
     */
    public function __construct($desc, $value) {
        $this->description = $desc;
        $this->value = $value;
    }

    public function setDescription($description) {
        $this->description = $description;
    }

    public function getDescription() {
        return $this->description;
    }

    public function setValue($value) {
        $this->value = $value;
    }

    public function getValue() {
        return $this->value;
    }


}
