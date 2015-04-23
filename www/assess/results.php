<?php
/**
 * Results page. This provides a list of Attempts that were made for a
 * particular Assessment, and the total marks obtained by the student.
 * Automatic marking of tests is handled here, where possible; otherwise, a
 * manual review is required to evaluate marks for certain questions which
 * cannot be automatically marked, e.g. short/long answer question types.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

/**
 * Process an Attempt row. Functionised because it is used in an iteration to
 * process students in the class, and then later used to deal with remainder
 * attempts in a separate iteration.
 *
 * @param  $assessHandler
 * @param  $attemptHandler
 * @param  $responseHandler
 * @param  $sectionHandler
 * @param  $currentAttempt
 * @param  $page
 * @param  $currentStudent
 * @param $previousAttempt
 *
 * @return void
 */
function processAttempt($assessHandler, $attemptHandler, $responseHandler, $sectionHandler, $currentAttempt, $page, $currentStudent, $previousAttempt, $readonly)
{
    $firstSection = $sectionHandler->getFirst($page->arg[0]);

    /* Finish time. Show the datetime when the person clicked Finish.
      * Otherwise, show the last time their responses were updated, or that
      * they never even attempted the test.
      */
    if ($currentAttempt['finish']) {
        $attemptFinishDate = new Alaress_Date_Format($currentAttempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);

        $finish = $attemptFinishDate->getShort();
        $timeTaken = $assessHandler->sec2hms($currentAttempt['timeTaken']);
    } else {
        $finish = $responseHandler->getLastSave($currentAttempt['attempt_id']);

        if (empty($finish)) {
            $finish = 'Unfinished';
        } else {
            $attemptFinishDate = new Alaress_Date_Format($finish, Alaress_Date_Format::DATABASE_FORMAT);

            $finish = $attemptFinishDate->getShort();
        }

        $timeTaken = 'Incomplete';
    }

    /* The total available marks for this attempt
      */
    $totalAvailableMarks = $attemptHandler->getTotalMarks($page->arg[0], $currentAttempt['attempt_id']);

    if ($totalAvailableMarks > 0) {
        $percentage = round(100 * $currentAttempt['totalMarks'] / $totalAvailableMarks);
    } else {
        $percentage = 0;
    }

    /* Check when the test was reviewed
      */
    if ($currentAttempt['reviewed_at'] != '') {
        $reviewedDate = new Alaress_Date_Format($currentAttempt['reviewed_at'], Alaress_Date_Format::DATABASE_FORMAT);

        $reviewedAt = 'Reviewed on ' . $reviewedDate->getShort();
    } else {
        $reviewedAt = 'Not reviewed';
    }

    /* Count how many responses haven't been automarked
      */
    $numNotAutomarked = $responseHandler->numNotAutomarked($currentAttempt['attempt_id']);

    $page->setVar(
        array(
            'ASSESS_ASSESS_ID' => $page->arg[0],
            'ASSESS_FIRST_SECTION_ID' => $firstSection['section_id'],
            'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
            'USER_ID' => $currentAttempt['user_id'],
            'ATTEMPT_EXTERNAL_ID' => $currentAttempt['synergy_id'],
            'ATTEMPT_FIRSTNAME' => $currentAttempt['firstname'],
            'ATTEMPT_SURNAME' => $currentAttempt['lastname'],
            'ATTEMPT_FINISH' => $finish,
            'ATTEMPT_TIME_TAKEN' => $timeTaken,
            'ATTEMPT_TOTAL_MARKS' => number_format($currentAttempt['totalMarks'], 2),
            'ATTEMPT_TOTAL_AVAILABLE_MARKS' => number_format($totalAvailableMarks, 2),
            'ATTEMPT_PERCENTAGE' => $percentage,
            'ATTEMPT_REVIEW_STATUS' => $reviewedAt,
            'ATTEMPT_UNMARKED_RESPONSES' => $numNotAutomarked,
            'ATTEMPT_REVIEW_LINK' => "<a href=\"/assess/review/{$page->arg[0]}/{$firstSection['section_id']}/{$currentAttempt['attempt_id']}\">Review</a>",
            'ATTEMPT_DELETE_LINK' => "<a href=\"/assess/deleteAttempt/{$currentAttempt['attempt_id']}\">Delete</a>",
        )
    );

    /* Expandable attempts
      */
    if (($previousAttempt == null) || ($previousAttempt['user_id'] != $currentAttempt['user_id'])) {
        $page->setVar(
            array(
                'ATTEMPT_EXPANDABLE' => '',
                'ATTEMPT_HIDDEN' => ''
            )
        );
    } else {
        $page->setVar(
            array(
                'ATTEMPT_EXPANDABLE' => 'expandable',
                'ATTEMPT_HIDDEN' => 'hide'
            )
        );
    }
    if ($readonly) {
        $page->setVar('assess_delete_block', '');
        $page->setVar('attempt_delete_block', '');
    } else {
        $page->parse('assess_delete_block', 'delete', false);
        $page->parse('attempt_delete_block', 'attempt_delete', false);
    }
    $page->parse('attempt_block', 'attempt', true);
}

/* Handlers and stuff
 */
$factory = new Assess_Factory();
$responseHandler = new Assess_Response();
$assessHandler = new Assess_Main();
$attemptHandler = new Assess_Attempt();
$sectionHandler = new Assess_Section();
$userHandler = new User();
$groupHandler = new Group();

$assess = $assessHandler->getByID($this->arg[0]);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$assessHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(125);
}

/* Set up template
 */
$this->setFile(TEMPLATE_content, 'assess/results.html');
$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
$this->setBlock('attempt', 'attempt_delete', 'attempt_delete_block');
$this->setBlock(TEMPLATE_content, 'modify_btn', 'modify_btn_block');
$this->setBlock(TEMPLATE_content, 'delete', 'delete_block');
$this->setBlock(TEMPLATE_content, 'has_attempt', 'has_attempt_block');

$this->setTitle('Results for: ' . $assess['title']);

$category = \Schoolbox\Resource\CategoryMapper::getInstance()->findById($assess['category_id']);

$this->setVar(
    array(
        'ASSESS_ASSESS_ID' => $assess['assess_id'],
        'ASSESS_TITLE' => Scrub::text($assess['title']),
        'CID' => $assess['category_id'],
        'CATEGORY_NAME' => Scrub::text($category->getName()),
        'DUEWORK_YEAR'     => date('Y', strtotime($assess['finish'])),
        'DUEWORK_SEMESTER' => DueWork_All::getSemester(strtotime($assess['finish'])),
    )
);

/**
 * If this assessment finishes in the past, don't allow editing except to superadmins
 */
$readonly = (!($groupHandler->isSuperuser() || (!(date('Y', strtotime($assess['finish'])) < date('Y')))));
$this->setVar(
    'ASSESS_ARCHIVED', (date('Y', strtotime($assess['finish'])) < date('Y')) ? '<b class="warning">Archived</b>' : ''
);

/* Get a list of students who could have submitted data to this Assess, then
 * join up all the attempts via php, so we can see who has submitted to this
 * Assess but are no longer in the class
 */


if (($category->getCodeString() != '') && Cfg::get('external_enable')) {
    /* If synergy code get synergy students
      */
    $synHandler = new External_Class();
    $allStudents = $synHandler->getStudents($category->getCodeString());
} else {
    //Retrieve student list from group membership
    $allStudents = $groupHandler->getMemberList($assess['category_id']);
}

/* Now construct list of data
 */
$allAttempts = $attemptHandler->getList($this->arg[0]);

/* If there are no attempts, hide the Review Horizontal and download buttons
 */
if (is_array($allAttempts) && count($allAttempts)) {
    $this->parse('has_attempt_block', 'has_attempt');
} else {
    $this->setVar('has_attempt_block', '');
}

/* Process everyone
 */
if (is_array($allStudents) && count($allStudents)) {
/* Process each student
      */
    foreach ($allStudents as $currentStudent) {
        $previousAttempt = null;
        $this->setVar('attempt_delete_block', '');

        // need to append uid if synergetic
        if ($currentStudent['synergy_id'] && !$currentStudent['uid']) {
            $user = $userHandler->getBySynergy($currentStudent['synergy_id']);
            $currentStudent['uid'] = $user['uid'];
        }

        /* Process all attempts for this student
           */
        if ($allAttempts && count($allAttempts)) {
            for ($numAttempts = 0, $i = 0; $i < count($allAttempts); $i++) {
                $currentAttempt = $allAttempts[$i];

                /* Match up attempt with student then remove from list
                     */
                if ($currentAttempt['user_id'] == $currentStudent['uid'] || $currentAttempt['synergy_id'] == $currentStudent['synergy_id']) {
                    if ($allAttempts[$i]['processed'] != true) {
                        processAttempt(
                            $assessHandler, $attemptHandler, $responseHandler, $sectionHandler, $currentAttempt, $this,
                            $currentStudent, $previousAttempt, $readonly
                        );

                        $allAttempts[$i]['processed'] = true;

                        $numAttempts++;

                        $previousAttempt = $currentAttempt;
                    }
                }
            }
        }

        /* If no attempts, just show name
           */
        if (!$numAttempts) {
            $this->setVar(
                array(
                    'ASSESS_FIRST_SECTION_ID' => '',
                    'ATTEMPT_ATTEMPT_ID' => '',
                    'USER_ID' => $currentStudent['uid'],
                    'ATTEMPT_EXTERNAL_ID' => $currentStudent['synergy_id'],
                    'ATTEMPT_FIRSTNAME' => $currentStudent['firstname'],
                    'ATTEMPT_SURNAME' => $currentStudent['lastname'],
                    'ATTEMPT_FINISH' => '',
                    'ATTEMPT_TIME_TAKEN' => '',
                    'ATTEMPT_TOTAL_MARKS' => '',
                    'ATTEMPT_TOTAL_AVAILABLE_MARKS' => '',
                    'ATTEMPT_PERCENTAGE' => '',
                    'ATTEMPT_REVIEW_STATUS' => '',
                    'ATTEMPT_UNMARKED_RESPONSES' => '',
                    'ATTEMPT_REVIEW_LINK' => '',
                    'ATTEMPT_DELETE_LINK' => '',
                    'ATTEMPT_EXPANDABLE' => '',
                    'ATTEMPT_HIDDEN' => '',
                )
            );

            $this->parse('attempt_block', 'attempt', true);
        }

        $numAttempts = 0;
    }
} else {
    $this->setVar('attempt_block', '');
    $this->setVar('assess_delete_block', '');
}

/* Deal with remainder attempts
 */
if ($allAttempts && count($allAttempts)) {
    $previousAttempt = null;

    foreach ($allAttempts as $currentAttempt) {
        if ($currentAttempt['processed'] != true) {
            processAttempt(
                $assessHandler, $attemptHandler, $responseHandler, $sectionHandler, $currentAttempt, $this,
                $userHandler->getByID($currentAttempt['user_id']), $previousAttempt, $readonly
            );

            $previousAttempt = $currentAttempt;
        }
    }
}

if ($readonly) {
    $this->setVar('delete_block', '');
    $this->setVar('modify_btn_block', '');
} else {
    $this->parse('delete_block', 'delete');
    $this->parse('modify_btn_block', 'modify_btn');
}
