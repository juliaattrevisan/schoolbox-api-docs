<?php

/**
 * Section creation.
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

    if (!$surveyHandler->isOwner($survey['assess_id']) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    if ($surveyHandler->isDeleted(Scrub::text($_POST['assess_id']))) {
        error(112);
    }

    /* Create new Section
     */
    $sectionHandler = new Survey_Section();

    $section = [
        'assess_id'   => (int)$_POST['assess_id'],
        'title'       => $_POST['title'],
        'sequence'    => (int)$_POST['sequence'],
        'description' => $_POST['description'],
    ];

    $section_id = $sectionHandler->create($section);

    /* Redirect to question creation for this section
     */
    SitePage::localRedir('/survey/question/create/' . $section_id);
} else {
    if (empty($this->arg[0])) {
        error(51);
    }

    if (!$surveyHandler->isValidUrlAccess($this->arg[0])) {
        $this->pageNotFound();
    }

    if ($surveyHandler->isDeleted($this->arg[0])) {
        error(112);
    }

    /* Find out whether the test exists to which you are attempting to add a
     * new Section
     */
    $survey = $surveyHandler->getByID($this->arg[0]);

    if (!$surveyHandler->isOwner($survey['assess_id']) && ($groupHandler->getPermission($survey['category_id']) < UserAccess::ACCESS_WRITE)) {
        error(130);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($survey['category_id']);
    } catch (\Exception $e) {
        error(117);
    }

    $assessStartDate  = new Alaress_Date_Format(strtotime($survey['start']), 'U');
    $assessFinishDate = new Alaress_Date_Format(strtotime($survey['finish']), 'U');

    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'survey/section/create.html');
    $this->setTitle('Create Section');

    $sectionHandler = new Survey_Section();

    $this->setVar(array(
        'ASSESS_ASSESS_ID'     => $this->arg[0],
        'ASSESS_KIND_LC'       => 'survey',
        'ASSESS_KIND'          => 'Survey',
        'ASSESS_TITLE'         => Scrub::text($survey['title']),
        'ASSESS_START_DATE'    => $assessStartDate->getLocalShort(),
        'ASSESS_FINISH_DATE'   => $assessFinishDate->getLocalShort(),
        'ASSESS_CATEGORY_ID'   => $folder->getId(),
        'ASSESS_CATEGORY_NAME' => Scrub::text($folder->getName()),
        'SECTION_ID'           => 0,
        'SEQUENCE'             => $sectionHandler->getNextSequence($this->arg[0])
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
            'text' => 'New Section',
            'href' => "",
            'class' => 'active',
        ]
    ]);
}
