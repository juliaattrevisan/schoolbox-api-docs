<?php
/**
 * Assessment completion. Marks when an assessment is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   2010 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 */

$surveyHandler   = new Survey_Main();
$attemptHandler  = new Survey_Attempt();
$questionHandler = new Survey_Question();
$responseHandler = new Survey_Response();
$groupHandler    = new Group();

if (!is_numeric($_SESSION['attempt_id'])) {
    SitePage::localRedir('/survey/intro/' . $this->arg[0]);
}

$survey  = $surveyHandler->getByID($this->arg[0]);
$attempt = $attemptHandler->getByID($_SESSION['attempt_id']);

if (!$survey) {
    error(112);
}

if ($surveyHandler->isDeleted($survey['assess_id'])) {
    error(112);
}

/* Disabled: doing so will allow teachers to give their test a "dry run" and
 * they can delete their attempts afterwards

if ($surveyHandler->isOwner($this->arg[0])) {
    error(124);
}
*/

if ($surveyHandler->isExpired($survey['assess_id'])) {
    error(120);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
if (!$surveyHandler->isOpen($survey) && ($groupHandler->getPermission($survey['category_id']) < WRITE_ACCESS)) {
    error(144);
}

$this->setFile(TEMPLATE_content, 'survey/end.html');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock(TEMPLATE_content, 'alreadyEnded', 'alreadyEnded_block');
$this->setTitle('Assessment: ' . $survey['title']);

if ($attempt['finish']) {
    $finishDate = Alaress_Date_Format::now();

    $attemptHandler->update(
        array(
            'attempt_id' => $_SESSION['attempt_id'],
            'finish'     => $finishDate->getDatabase(),
        )
    );

    $attemptEndDate = Alaress_Date_Format::now();

    $this->setVar(
        array(
            'ASSESS_TITLE'              => Scrub::text($survey['title']),
            'ASSESS_COMPLETION_MESSAGE' => Scrub::html($survey['completion_message']),
            'ASSESS_CATEGORY_ID'        => $survey['category_id'],
            'ATTEMPT_END'               => $attemptEndDate->getShort()
        )
    );

    $this->setVar('alreadyEnded_block', '');
    $this->parse('endAssess_block', 'endAssess');

    /* Notify the survey author when someone completes this
     */
    if ($survey['notify_author']) {
        $notification = new Survey_Notification($survey);
        $notification->send();
    }

} else {
    $attemptEndDate = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);

    $this->setVar(
        array(
            'ASSESS_TITLE'       => Scrub::text($survey['title']),
            'ASSESS_CATEGORY_ID' => $survey['category_id'],
            'ATTEMPT_END'        => $attemptEndDate->getShort()
        )
    );

    $this->parse('alreadyEnded_block', 'alreadyEnded');
    $this->setVar('endAssess_block', '');
}

/* Clear attempt
 */
$_SESSION['attempt_id'] = '';