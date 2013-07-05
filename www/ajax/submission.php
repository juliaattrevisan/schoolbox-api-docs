<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;

$page = new SitePage;

if (is_numeric($_REQUEST['id']) && is_numeric($_REQUEST['uid'])) {

    $subStudHandler = new SubmissionStudent;
    $groupHandler = new Group;

    $action = '';
    $output = array();
    if ($_REQUEST['is_assess']) {
        $attempt_mapper    = new AttemptMapper();
        $assessment_mapper = new OnlineAssessmentMapper();
        $assessment        = $assessment_mapper->findById($_REQUEST['id']);

        //
        // Has this student submitted for this assessment yet?
        // Adjust the message accordingly.
        $action = 'View Results';
        if ($_SERVER['REQUEST_TIME'] <= $assessment->getFinish()->getUnixTimestamp()) {
            $max_attempts = $assessment->getMaxAttempts();
            $attempts     = $attempt_mapper->getAttemptCountForStudent($assessment->getId(), $_REQUEST['uid']);
            if ($max_attempts <= 0 || $attempts < $max_attempts) {
                $action = ($attempts === 0) ? 'Sit' : 'Resit';
            }
        }

        $output = array(
            'id'          => $assessment->getId(),
            'cid'         => $assessment->getCategory()->getId(),
	        'uid'         => $_REQUEST['uid'],
            'category'    => Scrub::text($assessment->getCategory()->getName()),
            'typeName'    => Scrub::text($assessment->getWorkType()->getName()),
            'name'        => Scrub::text($assessment->getTitle()),
            'description' => '',
            'action'      => $action,
        );
    }
    else {
        $submission_mapper = new SubmissionBoxMapper();
        $submission        = $submission_mapper->findById($_REQUEST['id']);
        $submission_id     = $submission->getId();
        $submission_cat_id = $submission->getCategory()->getId();

        $current_time = new DateTime();

        /* Get status to set action */
        $student_submission = $subStudHandler->getStatus($_REQUEST['uid'], $submission_id);

        if ($student_submission['accept_submission']) {
            $action = 'View Details';
        }
        elseif ($student_submission['status'] == 1 && $current_time > $student_submission['open_date'] && $current_time < $student_submission['late_date']) {
            $action = 'Submit Work';
        }
        else {
            $action = 'View Details';
        }

        $output = array(
            'id'          => $submission_id,
            'cid'         => $submission_cat_id,
	        'uid'         => $_REQUEST['uid'],
            'category'    => Scrub::text($submission->getCategory()->getName()),
            'typeName'    => Scrub::text($submission->getWorkType()->getName()),
            'name'        => Scrub::text($submission->getName()),
            'description' => Scrub::html($submission->getDescription()),
            'action'      => $action,
        );
    }

    /* Check involved in subject */
    $groupHandler = new Group;
    if (!$groupHandler->involvedInSubject($output['cid']) && !$groupHandler->involvedInGroup($_REQUEST['uid'], $output['cid'])) {
        exit();
    }

}

header("Content-type: application/json");
print json_encode($output);
