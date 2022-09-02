<?php

/**
 * Survey creation. Surveys must always belong in a Category.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Breadcrumbs;

$groupHandler  = new Group();
$surveyHandler = new Survey_Main();

if (!empty($_POST)) {
    if ($groupHandler->getPermission(Scrub::text($_POST['category_id'])) < UserAccess::ACCESS_WRITE) {
        error(\Schoolbox\Error\Mapper::ERROR_PERM_TEST_MODIFY);
    }

    /* Format date, time
     */
    $start = Alaress_Date_Format::newLocal($_POST['start']['date']);
    $finish = Alaress_Date_Format::newLocal($_POST['finish']['date']);

    /* Check for valid dates
     */
    if (!$start->isValid() || !$finish->isValid()) {
        error(\Schoolbox\Error\Mapper::ERROR_INVALID_DATE);
    }

    /* Check that finish time cannot be before start time
     */
    if ($start->getUnixTimeStamp() >= $finish->getUnixTimeStamp()) {
        error(\Schoolbox\Error\Mapper::ERROR_START_TIME_GREATER);
    }

    $assess_id = $surveyHandler->create(
        array(
            'category_id'        => $_POST['category_id'],
            'user_id'            => $_SESSION['id'],
            'title'              => $_POST['title'],
            'description'        => $_POST['description'],
            'start'              => $start->getDatabase(),
            'finish'             => $finish->getDatabase(),
            'duration'           => Survey_Main::UNLIMITED_DURATION,
            'availability'       => 'survey',
            'anonymous_result'   => (int)($_POST['anonymous_result'] ?? false),
            'notify_author'      => (int)($_POST['notify_author'] ?? false),
            'max_attempts'       => $_POST['max_attempts'],
            'completion_message' => $_POST['completion_message'],
            'created_at'         => 'NOW()',
        )
    );

    if (!$assess_id) {
        error(\Schoolbox\Error\Mapper::ERROR_QUIZ_NOT_FOUND);
    }

    SitePage::localRedir('/survey/section/create/' . $assess_id);
} else {
    if (!is_numeric($this->arg[0])) {
        error(\Schoolbox\Error\Mapper::ERROR_CATEGORY_DELETED);
    }

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(\Schoolbox\Error\Mapper::ERROR_CATEGORY_DELETED);
        exit;
    }

    if ($groupHandler->getPermission($this->arg[0]) < UserAccess::ACCESS_WRITE) {
        error(\Schoolbox\Error\Mapper::ERROR_PERM_TEST_MODIFY);
    }

    /* Render create page
     */
    $this->setFile(TEMPLATE_content, 'survey/modify.html');
    $this->setBlock(TEMPLATE_content, 'durationHour', 'durationHour_block');
    $this->setBlock(TEMPLATE_content, 'durationMinute', 'durationMinute_block');
    $this->setBlock(TEMPLATE_content, 'importXML', 'importXML_block');

    $this->setTitle('Manage Survey');

    $assessDate    = new Alaress_Date_Format(time(), 'U');
    $assessEndDate = new Alaress_Date_Format(strtotime('+1 week'), 'U');

    $this->setVar(
        array(
            'ACTION'                          => 'create',
            'ASSESS_HEADING'                  => 'New Survey',
            'ASSESS_ACTION'                   => 'Create',
            'ASSESS_KIND_LC'                  => 'survey',
            'ASSESS_KIND'                     => 'Survey',
            'ASSESS_ASSESS_ID'                => '',
            'ASSESS_CATEGORY_ID'              => $folder->getId(),
            'ASSESS_CATEGORY_NAME'            => Scrub::text($folder->getName()),
            'ASSESS_TITLE'                    => '',
            'ASSESS_DESCRIPTION'              => '',
            'ASSESS_START_DATE'               => $assessDate->getLocal(),
            'ASSESS_FINISH_DATE'              => $assessEndDate->getLocal(),
            'ASSESS_ANONYMOUS_RESULT_CHECKED' => '',
            'ASSESS_NOTIFY_AUTHOR_CHECKED'    => '',
            'ASSESS_COMPLETION_MESSAGE'       => '',
            'HAS_ALL_QUESTIONS'               => 'large-12'
        )
    );

    /* Resit amounts
     */
    $this->setVar(
        array(
            'MAX_ATTEMPTS_CHECKED' => '',
        )
    );

    // Attachzone
    $zone = (new Zone('importXml'))
        ->setFileVar('importXml[]')
        ->setThumbnailSize(64)
        ->setModalTitle('Select XML')
        ->setAttachmentsLabel('')
        ->setAttachPrompt('Upload XML')
        ->setMimeFilter('text/xml,application/xml')
        ->setReadOnly(false)
        ->setMulti(false)
        ->useImportExpiryDate();
    $zone->renderIntoSitePage($this, 'ASSESS_IMPORT_ATTACH_ZONE');
    $this->parse('importXML_block', 'importXML');

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
            'text' => 'New Survey',
            'href' => '',
            'class' => 'active',
        ]
    ]);
}
