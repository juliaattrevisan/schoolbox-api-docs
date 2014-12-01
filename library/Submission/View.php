<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Submission_View extends Submission_Perspective {

    const UNSUBMITTED = 'Unsubmitted';
    const SUBMITTED   = 'Submitted';
    const RESUBMITTED = 'Resubmitted';
    const ON_TIME     = ' on time';
    const LATE        = ' late';

    const CLASS_UNSUBMITTED = 'submit-none';
    const CLASS_ON_TIME     = 'submit-ontime';
    const CLASS_LATE        = 'submit-late';


    /**
     * @param array $studentList
     * @param $submissionBoxID
     * @deps User, Submission, SubmissionStudent, SubmissionReturn
     */
    public function __construct($studentList, $submissionBoxID) {
        parent::__construct($studentList, array($submissionBoxID));

        $this->submissionBox = $this->submissionHandler->getByID($submissionBoxID);
        $this->marksheet = Marksheet_Factory::build($this->submissionBox['mark_type_slug'], $this->submissionBox);
    }


    public function getStudentInteractions($student) {
        // attempt lookup by id
        if ($student['uid']) {
            $user = $this->userHandler->getByID($student['uid']);
            $submissions = $this->submissionStudentHandler->getByUserID($this->submissionBox['id'], $student['uid']);
        }
        // attempt lookup by synergy
        elseif ($student['synergy_id']) {
            $user = $this->userHandler->getBySynergy($student['synergy_id']);
            $submissions = $this->submissionStudentHandler->getBySynergyID($this->submissionBox['id'], $student['synergy_id']);
        }
        // le fail
        else {
            return array();
        }

        if(!$user['uid']) {
            return array();
        }


        // if multi-submit has been disabled, only display the most recent submission
        if(!$this->getFromSubmissionBox('multi_submission') && $submissions) {
            $submissions = array($submissions[0]);
        }

        // set the user status
        if(!$submissions) {
            // 'cast' false to empty array
            $submissions = array();
            $user['status'] = self::UNSUBMITTED;
        }
        // multiple submissions
        elseif(count($submissions) > 1) {
            $user['status'] = self::RESUBMITTED;
        }
        else {
            $user['status'] = self::SUBMITTED;
        }


        // set the status colour
        if(!$submissions) {
            $user['class'] = self::CLASS_UNSUBMITTED;
        }
        else {
            // get status from the most recent submission
            $submitDate = strtotime($submissions[0]['datestamp']);
            $closeDate = strtotime($this->getFromSubmissionBox('date'));

            if($submitDate < $closeDate) {
                $user['class'] = self::CLASS_ON_TIME;
                $user['status'] .= self::ON_TIME;
            }
            else {
                $user['class'] = self::CLASS_LATE;
                $user['status'] .= self::LATE;
            }
        }


        // append the submissions to the student array
        $user['submissions'] = $submissions;


        // get returns
        $returns = $this->submissionReturnHandler->getByUser($user['uid'], $this->getFromSubmissionBox('id'));

        if(!$returns) {
            $returns = array();
        }

        $user['returns'] = $returns;

        printf('<pre style="margin-left: 300px;">%s</pre>', print_r($user, true));

        return $user;
    }


    /**
     * @param $field
     * @return mixed
     */
    public function getFromSubmissionBox($field) {
        return parent::getFromSubmissionBox(0, $field);
    }

    public function insertMarkingTemplate(SitePage $page, $handle, $student) {
        parent::insertMarkingTemplate($page, $handle, 0, $student);
    }
}
