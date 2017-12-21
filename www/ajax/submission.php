<?php

require_once dirname(__FILE__) . '/../../library/Bootstrap.php';

use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;

$page = new SitePage;

if (is_numeric($_REQUEST['id']) && is_numeric($_REQUEST['uid'])) {

    $subStudHandler = new SubmissionStudent;
    $groupHandler = new Group;

    $action = '';
    $output = array();
    if ($_REQUEST['is_assess']) {
        $attempt_mapper    = new QuizAttemptMapper();
        $assessment_mapper = new QuizMapper();
        try {
            $assessment        = $assessment_mapper->findById($_REQUEST['id']);

            //
            // Has this student submitted for this assessment yet?
            // Adjust the message accordingly.
            $action = 'View Results';
            if ($_SERVER['REQUEST_TIME'] <= $assessment->getFinish()->getUnixTimeStamp()) {
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
                'typeName'    => $assessment->getWorkType() ? Scrub::text($assessment->getWorkType()->getName()) : "",
                'name'        => Scrub::text($assessment->getTitle()),
                'description' => '',
                'action'      => $action,
            );
        } catch(Exception $e) {
            exit;
        }
    }
    else {
        $submission_mapper = new SubmissionBoxMapper();
        try {
            $submission        = $submission_mapper->findById($_REQUEST['id']);
            $submission_id     = $submission->getId();
            $submission_cat_id = $submission->getCategory()->getId();

            $current_time = new DateTime();

            /* Get status to set action */
            $student_submission = $subStudHandler->getStatus($_REQUEST['uid'], $submission_id);

            if($submission->getAcceptOnline() &&
               $submission->getOpenDate()->getDateTimeObject() < $current_time &&
               $submission->getLateDate()->getDateTimeObject() > $current_time &&
               $student_submission['status'] < 2) {
                $action = 'Submit';
            }
            else {
                $action = 'View Details';
            }

            $workType = $submission->getAssessmentModel()->getWorkType();

            $output = array(
                'id'          => $submission_id,
                'cid'         => $submission_cat_id,
                'uid'         => $_REQUEST['uid'],
                'category'    => Scrub::text($submission->getCategory()->getName()),
                'typeName'    => $workType ? Scrub::text($workType->getName()) : '',
                'name'        => Scrub::text($submission->getName()),
                'description' => Scrub::html($submission->getDescription()),
                'action'      => $action,
            );
        } catch(Exception $e) {
            exit();
        }
    }

    /* Check involved in subject */
    $groupHandler = new Group;

    /* Students must always be involved
     */
    $involvedCategories = $groupHandler->involvedInCategoryList($_REQUEST['uid']);

    $isStudentInvolved = false;

    foreach ($involvedCategories as $currentCategory) {
        if ($output['cid'] == $currentCategory['id']) {
            $isStudentInvolved = true;

            break;
        }
    }

    if ($isStudentInvolved) {
        if ($_SESSION['user']->getRole()->isParent()) {

            /* Parents must be owners of a child and Hide Work From Parents
             * (restrict_student_submissions) disabled in order to see bubbles
             */
            $isParentOfChild = $groupHandler->isParentOfChild($_REQUEST['uid']);
            $isRestricted = Cfg::get('restrict_student_submissions');

            if (!$isParentOfChild || $isRestricted) {
                exit();
            }
        }
        /* Be a bad host
         */
        else if ($_SESSION['user']->getRole()->isGuest()) {
            exit();
        }
    }
    else {
        exit();
    }
}

// just remove parent links, don't even worry about the config flag
if ($_SESSION['user']->getRole()->isParent()) {
    $output['action'] = '';
}

header("Content-type: application/json");
print json_encode($output);
