<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * Class DueWork_Rubric_Criterion
 * @deprecated use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion
 */
class DueWork_Rubric_Criterion {

    private $id;        // int
    private $grouping;  // string
    private $name;      // string


    /**
     * DueWork_Rubric_Criterion constructor.
     * @deprecated
     *
     * @param $id
     * @param $grouping
     * @param $name
     */
    public function __construct($id, $grouping, $name) {
        $this->id = $id;
        $this->grouping = $grouping;
        $this->name = $name;
    }

    public function setGrouping($grouping) {
        $this->grouping = $grouping;
    }

    public function getGrouping() {
        return $this->grouping;
    }

    public function setName($name) {
        $this->name = $name;
    }

    public function getName() {
        return $this->name;
    }

    public function setID($id) {
        $this->id = $id;
    }

    public function getID() {
        return $this->id;
    }

}
