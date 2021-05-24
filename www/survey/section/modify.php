<?php

/**
 * Section modification.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Breadcrumbs;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

$surveyHandler = new Survey_Main();
$groupHandler = new Group();

if (!empty($_POST)) {
    if (!$surveyHandler->isValidUrlAccess(Scrub::text($_POST['assess_id']))) {
        $this->pageNotFound();
    }

    $survey = $surveyHandler->getByID(Scrub::text($_POST['assess_id']));

    if ($surveyHandler->isDeleted($_POST['assess_id'])) {
        error(112);
    }

    if (!$surveyHandler->isOwner($survey['assess_id']) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    $sectionHandler = new Survey_Section();

    $sectionHandler->update(array(
        'assess_id' => $_POST['assess_id'],
        'section_id' => $_POST['section_id'],
        'sequence' => $_POST['sequence'],
        'title' => $_POST['title'],
        'random' => (int)$_POST['random'],
        'pool' => (int)$_POST['pool'],
        'description' => $_POST['description'],
    ));

    /* Redirect to summary
     */
    SitePage::localRedir('/survey/summary/' . Scrub::text($_POST['assess_id']));
} else {
    if (!is_numeric($this->arg[0])) {
        error(112);
    }

    $sectionHandler = new Survey_Section();
    $section = $sectionHandler->getByID($this->arg[0]);

    if (!$section) {
        error(112);
    }

    if (!$surveyHandler->isValidUrlAccess($section['assess_id'])) {
        $this->pageNotFound();
    }

    $survey = $surveyHandler->getByID($section['assess_id']);

    if (!$surveyHandler->isOwner($section['assess_id']) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    if ($surveyHandler->isDeleted($section['assess_id'])) {
        error(112);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($survey['category_id']);
    } catch (\Exception $e) {
        error(13);
    }

    $assessStartDate  = new Alaress_Date_Format(strtotime($survey['start']), 'U');
    $assessFinishDate = new Alaress_Date_Format(strtotime($survey['finish']), 'U');

    /* Render modify page
     */
    $this->setFile(TEMPLATE_content, 'survey/section/modify.html');
    $this->setBlock(TEMPLATE_content, 'allQuestions', 'allQuestions_block');
    $this->setTitle('Create Section');

    $this->setVar(array(
        'ASSESS_ASSESS_ID'     => $section['assess_id'],
        'ASSESS_KIND_LC'       => 'survey',
        'ASSESS_KIND'          => 'Survey',
        'ASSESS_TITLE'         => Scrub::text($survey['title']),
        'ASSESS_START_DATE'    => $assessStartDate->getLocalShort(),
        'ASSESS_FINISH_DATE'   => $assessFinishDate->getLocalShort(),
        'ASSESS_CATEGORY_ID'   => $folder->getId(),
        'ASSESS_CATEGORY_NAME' => Scrub::text($folder->getName()),
        'SECTION_ID'           => $section['section_id'],
        'SECTION_TITLE'        => Scrub::text($section['title']),
        'DESCRIPTION'          => Scrub::html($section['description']),
        'SEQUENCE'             => $section['sequence'],
        'RANDOM_SELECTED'      => $section['random'] == 0 ? '' : 'checked=\'checked\'',
        'POOL'                 => $section['pool'] > 0 ? $section['pool'] : ''
    ));

    /* Render out breadcrumbs
     */
    $trail = new Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => $survey['title'],
            'href' => "/survey/summary/{$survey['assess_id']}",
            'class' => '',
        ],
        [
            'text' => $section['title'],
            'href' => "",
            'class' => 'active',
        ]
    ]);
}
