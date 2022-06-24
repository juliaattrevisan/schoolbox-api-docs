<?php

/**
 * Assessment completion. Marks when an assessment is finished.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Breadcrumbs;
use Schoolbox\Permission\Access\UserAccess;

$surveyHandler   = new Survey_Main();
$attemptHandler  = new Survey_Attempt();
$questionHandler = new Survey_Question();
$responseHandler = new Survey_Response();
$groupHandler    = new Group();

/** @var SitePage $this */
if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey    = $surveyHandler->getByID($this->arg[0]);
$attempt   = $attemptHandler->getByID($this->arg[1]);
$attemptId = $attempt['attempt_id'];
if (!is_numeric($attemptId)) {
    SitePage::localRedir('/survey/intro/' . $this->arg[0]);
}


if ($surveyHandler->isDeleted($survey['assess_id'])) {
    error(\Schoolbox\Error\Mapper::ERROR_QUIZ_NOT_FOUND);
}

// Sanity check on attempt - does it belong to the current user and survey?
if (!($attempt['user_id'] == $_SESSION['user']->getId() && $attempt['assess_id'] == $survey['assess_id'])) {
    error(\Schoolbox\Error\Mapper::ERROR_ATTEMPT_NOT_RECORDED);
}

/* Disabled: doing so will allow teachers to give their test a "dry run" and
 * they can delete their attempts afterwards

if ($surveyHandler->isOwner($this->arg[0])) {
    error(124);
}
*/

if ($surveyHandler->isExpired($survey['assess_id'])) {
    error(\Schoolbox\Error\Mapper::ERROR_QUIZ_EXPIRED);
}

/* Participants should be blocked from attempting if it hasn't started,
 * but authors should be able to test out their content
 */
if (!$surveyHandler->isOpen($survey) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(\Schoolbox\Error\Mapper::ERROR_SURVEY_NOT_OPEN);
}

try {
    $folderMap = Schoolbox\Resource\Folder\Mapper::getInstance();
    $folder    = $folderMap->findById($survey['category_id']);
} catch (\Exception $e) {
    error(\Schoolbox\Error\Mapper::ERROR_CATEGORY_DELETED);
}

$this->setFile(TEMPLATE_content, 'survey/end.html');
$this->setBlock(TEMPLATE_content, 'endAssess', 'endAssess_block');
$this->setBlock(TEMPLATE_content, 'alreadyEnded', 'alreadyEnded_block');
$this->setBlock(TEMPLATE_content, 'completionMessage', 'completionMessage_block');

$this->setTitle('Survey: ' . Scrub::text($survey['title']));

$this->setVar(
    array(
        'ASSESS_TITLE'              => Scrub::text($survey['title']),
        'ASSESS_COMPLETION_MESSAGE' => Scrub::html($survey['completion_message']),
        'ASSESS_CATEGORY_ID'        => $survey['category_id'],
    )
);

if ($survey['completion_message']) {
    $this->parse('completionMessage_block', 'completionMessage');
} else {
    $this->setVar('completionMessage_block', '');
}

if (!$attempt['finish']) {
    $finishDate = Alaress_Date_Format::now();

    $attemptHandler->update(
        array(
            'attempt_id' => $attemptId,
            'finish'     => $finishDate->getDatabase(),
        )
    );

    $this->setVar('ATTEMPT_END', $finishDate->getShort());

    $this->setVar('alreadyEnded_block', '');
    $this->parse('endAssess_block', 'endAssess');

    /* Notify the survey author when someone completes this
     */
    if ($survey['notify_author']) {
        $notification = new Survey_Notification($survey, $attempt);
        $notification->send();
    }
} else {
    $finishDate = new Alaress_Date_Format($attempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);

    $this->setVar('ATTEMPT_END', $finishDate->getShort());

    $this->parse('alreadyEnded_block', 'alreadyEnded');
    $this->setVar('endAssess_block', '');
}

/** @var \Schoolbox\Resource\Folder\Model $folder */
$trail = new Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => $folder->getName(),
        'href'  => '/homepage/' . $folder->getId(),
        'class' => ''
    ],
    [
        'text'  => $survey['title'],
        'href'  => '/survey/intro/' . $survey['assess_id'],
        'class' => 'active'
    ],
]);
