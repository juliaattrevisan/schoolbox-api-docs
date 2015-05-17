<?php
/**
 * Assessment completion. Marks when an assessment is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Component\OnlineAssessment\Attempt;
use Schoolbox\Assessment\Component\OnlineAssessment\AttemptMapper;
use Schoolbox\Assessment\Course\Task;

$assessHandler   = new Assess_Main();
$attemptHandler  = new Assess_Attempt();
$questionHandler = new Assess_Question();
$responseHandler = new Assess_Response();
$sectionHandler  = new Assess_Section();
$groupHandler    = new Group();

if (!is_numeric($_SESSION['attempt_id'])) {
    SitePage::localRedir('/assess/intro/' . $this->arg[0]);
}

$assess  = $assessHandler->getByID($this->arg[0]);
$attempt = $attemptHandler->getByID($_SESSION['attempt_id']);

if (!$assess) {
    error(112);
}

if ($assessHandler->isDeleted($assess['assess_id'])) {
    error(112);
}

/* Disabled: doing so will allow teachers to give their test a "dry run" and
 * they can delete their attempts afterwards

if ($assessHandler->isOwner($this->arg[0])) {
    error(124);
}
*/

if ($assessHandler->isExpired($assess['assess_id'])) {
    error(120);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
if (!$assessHandler->isOpen($assess) && ($groupHandler->getPermission($assess['category_id']) < WRITE_ACCESS)) {
    error(143);
}

$this->setFile(TEMPLATE_content, 'assess/end.html');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock(TEMPLATE_content, 'alreadyEnded', 'alreadyEnded_block');
$this->setBlock(TEMPLATE_content, 'unmarkedRemaining', 'unmarkedRemaining_block');
$this->setBlock(TEMPLATE_content, 'has_marks', 'has_marks_block');
$this->setBlock(TEMPLATE_content, 'view_attempt', 'view_attempt_block');

$this->setTitle('Assessment: ' . $assess['title']);

$this->setVar(array(
    'ASSESS_TITLE' => Scrub::text($assess['title']),
    'ASSESS_CATEGORY_ID' => $assess['category_id'],
    'ASSESS_COMPLETION_MESSAGE' => Scrub::html($assess['completion_message']),
));

if (!$attempt['finish']) {
    $attemptFinishDate = Alaress_Date_Format::now();

    /** @var Attempt $attempt */
    $attempt = AttemptMapper::getInstance()->findById($_SESSION['attempt_id']);

    $attempt->setFinish($attemptFinishDate);

    AttemptMapper::getInstance()->save($attempt);

    $this->setVar('ATTEMPT_END', $attemptFinishDate->getShort());

    // Does this item have a task instance associated? if so, set it as
    // completed (pending, instead of completed?)
    if (!empty($assess['task_instance_id'])) {
        Task\InstanceCompletionMapper::getInstance()->markAsCompleted(
            $assess['task_instance_id'],
            $attempt->getUser()->getId()
        );
    }

    $this->setVar('alreadyEnded_block', '');
    $this->parse('endAssess_block', 'endAssess');
} else {
    $attemptFinishDate = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);
    $this->setVar('ATTEMPT_END', $attemptFinishDate->getShort());
    $this->parse('alreadyEnded_block', 'alreadyEnded');
    $this->setVar('endAssess_block', '');
}

/* Perform automated marking on responses which support this feature. Only
 * mark responses which haven't been automarked previously, which allows
 * teachers to customise response marks without being overwritten again.
 *
 * Once automated marking has been performed, remember which responses were
 * affected so we don't refetch them the next time the page loads.
 */
$allAttempts = $attemptHandler->getListByAssessAndAttempt($this->arg[0], $_SESSION['attempt_id']);

$factory = new Assess_Factory();

if ($allAttempts) {
    foreach ($allAttempts as $currentAttempt) {
        $allResponses = $responseHandler->getAutomarkableResponses($_SESSION['attempt_id']);

        if (is_array($allResponses)) {
            foreach ($allResponses as $currentResponse) {
                $answerHandler = $factory->createAnswerModel($currentResponse['type_id']);

                $answerHandler->mark($currentResponse);

                $markedResponseIDs[] = $currentResponse['response_id'];
            }
        }
    }
}

$responseHandler->updatedAutomatedMarks($markedResponseIDs);

/* Show mark for the attempt you just did
 */
$marks = $attemptHandler->getMarksForAttempt($_SESSION['attempt_id']);
$total = $attemptHandler->getTotalMarks($this->arg[0], $_SESSION['attempt_id']);

if ($total > 0) {
    $percentage = 100 * number_format($marks / $total, 2);
} else {
    $percentage = 0;
}

$numMarkedQuestions     = $responseHandler->numAutomarked($_SESSION['attempt_id']);
$numUnmarkedQuestions   = $responseHandler->numNotAutomarked($_SESSION['attempt_id']);
$valueUnmarkedQuestions = $responseHandler->valueNotAutomarked($_SESSION['attempt_id']);

$this->setVar(
    array(
        'ATTEMPT_MARKS'                    => $marks,
        'ATTEMPT_PERCENTAGE'               => $percentage,
        'ATTEMPT_TOTAL'                    => $total,
        'ATTEMPT_NUM_MARKED_QUESTIONS'     => $numMarkedQuestions,
        'ATTEMPT_NUM_UNMARKED_QUESTIONS'   => $numUnmarkedQuestions,
        'ATTEMPT_NUM_QUESTIONS'            => $numMarkedQuestions + $numUnmarkedQuestions,
        'ATTEMPT_VALUE_UNMARKED_QUESTIONS' => $valueUnmarkedQuestions,
    )
);

/* If there are unmarked questions remaining, show the block
 */
if ($numUnmarkedQuestions) {
    $this->parse('unmarkedRemaining_block', 'unmarkedRemaining');
} else {
    $this->setVar('unmarkedRemaining_block', '');
}

/* View Attempt link
 */
$firstSection = $sectionHandler->getFirst($assess['assess_id']);

$this->setVar(
    array(
        'ASSESS_ID'  => $assess['assess_id'],
        'SECTION_ID' => $firstSection['section_id'],
        'ATTEMPT_ID' => $_SESSION['attempt_id']
    )
);

/* Visibility
 */
$visibility = new \Assess\Visibility();
$visibility->setId($assess['mark_visibility']);

if ($visibility->isStaff() && $groupHandler->isStaff()) {
    $this->parse('has_marks_block', 'has_marks');
    $this->parse('view_attempt_block', 'view_attempt');
} else {
    if ($visibility->isStaffStudent() && ($groupHandler->isStaff() || $groupHandler->isStudent())) {
        $this->parse('has_marks_block', 'has_marks');
        $this->parse('view_attempt_block', 'view_attempt');
    } else {
        if ($visibility->isStaffStudentParent() && ($groupHandler->isStaff() || $groupHandler->isStudent())) {
            $this->parse('has_marks_block', 'has_marks');
            $this->parse('view_attempt_block', 'view_attempt');
        } else {
            $this->setVar(
                array(
                    'view_attempt_block' => '',
                    'has_marks_block'    => '',
                )
            );
        }
    }
}

/* Clear attempt
 */
$_SESSION['attempt_id'] = '';
