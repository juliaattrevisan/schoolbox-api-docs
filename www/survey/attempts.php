<?php
/**
 * Attempts
 *
 * Show a list of attempts for a particular Survey.
 * You can delete attempts from this screen.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Breadcrumbs;

$groupHandler = new Group();
$surveyHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$attemptHandler = new Survey_Attempt();

if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getByID($this->arg[0]);

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < WRITE_ACCESS)) {
    error(126);
}

if ($surveyHandler->isDeleted($this->arg[0])) {
    error(112);
}

try {
    $folder = Schoolbox\Resource\Folder\Mapper::getInstance()->findById($survey['category_id']);
} catch (Schoolbox\Mapper\Exception $e) {
    error(126);
    exit;
}

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'survey/attempts.html');
$this->setBlock(TEMPLATE_content, 'attempt', 'attempt_block');
$this->setBlock(TEMPLATE_content, 'respondent_heading', 'respondent_heading_block');
$this->setBlock('attempt', 'respondent_name', 'respondent_name_block');

/* If not using Anonymous Result, set respondent details
 */
if ($survey['anonymous_result']) {
    $this->setVar('respondent_heading_block', '');
} else {
    $this->parse('respondent_heading_block', 'respondent_heading');
}

/* Generic details
 */
$this->setVar(array(
    'SURVEY_NAME' => Scrub::text($survey['title']),
    'CID' => $survey['category_id'],
    'SURVEY_ID' => $survey['assess_id']
));

/* Default
 */
$this->setVar('attempt_block', '');

/* Iterate
 */
$allAttempts = $attemptHandler->getList($this->arg[0]);

if (is_array($allAttempts) && count($allAttempts)) {
    $i = 0;

    foreach ($allAttempts as $currentAttempt) {
        $i++;

        $startDate = new Alaress_Date_Format($currentAttempt['start'], Alaress_Date_Format::DATABASE_FORMAT);

        /* For attempts that are still in progress
           */
        if ($currentAttempt['finish']) {
            $finishDate = new Alaress_Date_Format($currentAttempt['finish'], Alaress_Date_Format::DATABASE_FORMAT);
            $finishDateValue = $finishDate->getShort();
        } else {
            $finishDateValue = 'Unfinished';
        }

        $firstSection = $sectionHandler->getFirst($this->arg[0]);

        $this->setVar(array(
            'ATTEMPT_USER_ID' => $currentAttempt['user_id'],
            'ATTEMPT_FIRSTNAME' => $currentAttempt['firstname'],
            'ATTEMPT_LASTNAME' => $currentAttempt['lastname'],
            'ATTEMPT_START' => $startDate->getShort(),
			'ATTEMPT_START_TIMESTAMP' => $startDate->getUnixTimeStamp(),
            'ATTEMPT_FINISH' => $finishDateValue,
			'ATTEMPT_FINISH_TIMESTAMP' => $finishDate instanceof Alaress_Date_Format ? $finishDate->getUnixTimeStamp() : 0,
            'ATTEMPT_ASSESS_ID' => $this->arg[0],
            'ATTEMPT_SECTION_ID' => $firstSection['section_id'],
            'ATTEMPT_ATTEMPT_ID' => $currentAttempt['attempt_id'],
            'ON_OFF' => $i % 2 ? 'dark' : ''
        ));

        /* If not using Anonymous Result, set respondent details
           */
        if ($survey['anonymous_result']) {
            $this->setVar('respondent_name_block', '');
        } else {
            $this->parse('respondent_name_block', 'respondent_name', true);
        }

        $this->parse('attempt_block', 'attempt', true);

        /* Clear children after parsing out the parent
           */
        $this->setVar('respondent_name_block', '');
    }
} else {
    $this->setBlock(TEMPLATE_content, 'checkbox', 'checkbox_block');
    $this->setVar('checkbox_block','');
    $this->setBlock(TEMPLATE_content, 'buttons', 'buttons_block');
    $this->setVar('buttons_block','');
}

$trail = new Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text'  => $folder->getName(),
        'href'  => '/homepage/' . $folder->getId(),
        'class' => ''
    ],
    [
        'text'  => $survey['title'],
        'href'  => '/survey/summary/' . $survey['assess_id'],
        'class' => ''
    ],
    [
        'text'  => 'Attempts',
        'href'  => '#',
        'class' => 'active'
    ],

]);
