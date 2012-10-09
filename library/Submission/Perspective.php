<?php
/**
 * Examples of the views that will implement this are
 * - /submission/viewSubmission/:boxID (single box)
 * - /submission/markSheet/:categoryID (multiple boxes and assessments)
 *
 * The common functionality between them is provided in this class: display a list of students and their work (may be one, may be more)
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Submission_Perspective {

    /**
     * @var array
     */
    protected $studentList;

    /**
     * @var array
     */
    protected $submissionBoxList;

    /**
     * @var Marksheet_Abstract
     */
    protected $marksheet;

    /**
     * @var Submission
     */
    protected $submissionHandler;

    /**
     * @var User
     */
    protected $userHandler;

    /**
     * @var SubmissionStudent
     */
    protected $submissionStudentHandler;

    /**
     * @var SubmissionReturn
     */
    protected $submissionReturnHandler;


    /**
     * studentList should be an array, each element also being an array, representative of the student
     * @param array $studentList
     * @param array $submissionBoxList
     * @deps User, Submission, SubmissionReturn, SubmissionStudent
     */
    public function __construct($studentList, $submissionBoxList) {
        if(is_array($studentList)) {
            $this->studentList = $studentList;
        }
        else {
            $this->studentList = array();
        }
        if(is_array($submissionBoxList)) {
            $this->submissionBoxList = $submissionBoxList;
        }
        else {
            $this->submissionBoxList = array();
        }

        // <deps>
        $this->userHandler = new User();
        $this->submissionHandler = new Submission();
        $this->submissionReturnHandler = new SubmissionReturn();
        $this->submissionStudentHandler = new SubmissionStudent();
        // </deps>
    }

    public function rewind() {
        reset($this->studentList);
    }

    /**
     * @return array
     */
    public function nextStudent() {
        if(next($this->studentList)) {
            return current($this->studentList);
        }
        else {
            return false;
        }
    }

    public function countStudents() {
        return count($this->studentList);
    }

    /**
     * @return mixed
     */
    public function currentStudent($field) {
        $student = current($this->studentList);
        if($field) {
            return $student[$field];
        }
        else {
            return $student;
        }
    }

    /**
     * @return bool
     */
    public function isReadOnly() {
        return false;
        //return !$groupHandler->isSuperuser() && $dueDate->getUsingFormat('Y') < date('Y');
    }

    /**
     * @return bool
     */
    public function isArchived() {
        return false;
        // return $dueDate->getUsingFormat('Y') < date('Y');
    }

    /**
     * @param $boxID
     * @param $field
     * @return mixed
     */
    public function getFromSubmissionBox($boxID, $field) {
        if(isset($this->submissionBoxList[$boxID][$field])) {
            return $this->submissionBoxList[$boxID][$field];
        }
        return false;
    }

    public function insertMarkingTemplate(SitePage $page, $handle, $boxID, $student) {
        $mark = $this->marksheet->retrieveMark($student['uid'], $boxID);

        $page->setFile('markingTpl', 'submission/marktype/writable.html');

        // parse the appropraite block, and hide the rest
        foreach($this->submissionHandler->getMarkTypes(true) as $type) {
            $page->setBlock('markingTpl', "mark_type_{$type['slug']}", "mark_type_{$type['slug']}_block");

            // if it's not the template we're after, hide it
            if($type['mark_type_id'] != $this->marksheet->getID()) {
                $page->setVar("mark_type_{$type['slug']}_block", '');
            }
            else {
                $slug = $type['slug'];
            }
        }

        if(isset($slug)) {
            $this->marksheet->outputUI($page, $mark);

            printf('<pre style="margin-left:300px;">%s</pre>', print_r($mark, true));

            $page->parse("mark_type_{$slug}_block", "mark_type_{$slug}", false);
        }

        $page->parse($handle, 'markingTpl');
    }
}
