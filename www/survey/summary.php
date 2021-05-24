<?php

/**
 * A Summary page for assessment creation methods to return to.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Breadcrumbs;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Assessment\Redirect;

$surveyHandler = new Survey_Main();
$sectionHandler = new Survey_Section();
$attemptHandler = new Survey_Attempt();
$groupHandler = new Group();
if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
    $this->pageNotFound();
}

$survey = $surveyHandler->getIntro($this->arg[0]);
$allSummary = $surveyHandler->getIntroSummary($this->arg[0]);
$allSections = $sectionHandler->getList($survey['assess_id']);
if ($surveyHandler->isDeleted($this->arg[0])) {
    error(112);
}

if (!$surveyHandler->isOwner($this->arg[0]) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
    error(125);
}

try {
    $folderMapper = new FolderMapper();
    $folder = $folderMapper->findById($survey['category_id']);
} catch (\Exception $e) {
    error(13);
    exit;
}

$assessStartDate = new Alaress_Date_Format(strtotime($survey['start']), 'U');
$assessFinishDate = new Alaress_Date_Format(strtotime($survey['finish']), 'U');
$this->setVar(array(
    'ASSESS_ASSESS_ID'     => $this->arg[0],
    'ASSESS_KIND_LC'       => 'survey',
    'ASSESS_KIND'          => 'Survey',
    'ASSESS_TITLE'         => Scrub::text($survey['title']),
    'ASSESS_DESCRIPTION'   => Scrub::html($survey['description']),
    'ASSESS_START'         => $assessStartDate->getShort(),
    'ASSESS_FINISH'        => $assessFinishDate->getShort(),
    'FIRST_SECTION'        => $allSections[0]['section_id'],
    'ASSESS_CATEGORY_ID'   => $folder->getId(),
    'ASSESS_CATEGORY_NAME' => Scrub::text($folder->getName()),
    'RETURN_URL_NAME'      => Redirect::RETURN_URL,
    'RETURN_URL'           => '',
    'MODIFY_URL'           => "/survey/modify/{$this->arg[0]}",
    'RETURN_URL_MODIFY'    => "?" . Redirect::RETURN_URL . '=' . Redirect::encode("/survey/summary/{$this->arg[0]}"),
    'RETURN_URL_DECODED'   => "/homepage/{$folder->getId()}",
));
$this->setFile(TEMPLATE_content, 'assess/summary.html');
$this->setFile('assessContentsFragment', 'assess/contents.html');
$this->setBlock(TEMPLATE_content, 'has_description', 'has_description_block');
$this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
$this->setBlock(TEMPLATE_content, 'section', 'section_block');
$this->setBlock(TEMPLATE_content, 'delete_quiz', 'delete_quiz_block');
$this->setBlock(TEMPLATE_content, 'delete_survey', 'delete_survey_block');
$this->setTitle('Assessment: ' . Scrub::text($survey['title']));
$survey['description'] = trim($survey['description']);
if (empty($survey['description'])) {
    $this->setVar('has_description_block', '');
} else {
    $this->parse('has_description_block', 'has_description', true);
}
$surveyHandler->renderAssessContents($survey['assess_id'], $this);
$this->parse('allQuestions_block', 'assessContentsFragment', true);
$this->setVar(array(
    'ASSESS_DURATION' => $survey['duration'] == Survey_Main::UNLIMITED_DURATION ? 'Unlimited' : $surveyHandler->sec2hms($survey['duration']),
));
$this->setVar('delete_quiz_block', '');
$this->parse('delete_survey_block', 'delete_survey');
/* Render out breadcrumbs
 */
$trail = new Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => $folder->getName(),
        'href' => "/homepage/{$folder->getId()}",
        'class' => '',
    ],
    [
        'text' => $survey['title'],
        'href' => "/survey/summary/{$survey['assess_id']}",
        'class' => 'active',
    ]
]);
