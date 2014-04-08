<?php
/**
 * Model for rubric result, these make up results in a result set that can be used to populate a rubric
 *
 * @author    James Leckie <james.leckie@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Component\SubmissionBox\Rubric;

use Schoolbox\Assessment\Component\SubmissionBox;

class Result {

    /**
     * @var string
     */
    protected $label;
    /**
     * @var int
     */
    protected $value;

    /**
     * @param $label
     */
    public function setLabel($label) {
        $this->label = $label;
    }

    /**
     * @return int
     */
    public function getLabel() {
        return $this->label;
    }

    /**
     * @param $value
     */
    public function setValue($value) {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getValue() {
        return $this->value;
    }


}

