<?php

require_once $_SERVER['DOCUMENT_ROOT'] . '/../include/Bootstrap.php';

use \Schoolbox\Breadcrumbs;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;

$pollHandler = new Poll;
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($this->arg[0])) {

    $poll = $pollHandler->getByID($this->arg[0]);

    if (!isset($poll['pollID'])) {
        error(101);
    }

    // check permission
    if ($groupHandler->getPermission($poll['categoryID']) < WRITE_ACCESS) {
        error(19);
    }

    $this->setFile(TEMPLATE_content, "poll/modify.html");
    $this->setTitle('Modify Poll');

    $closeDate = new Alaress_Date_Format($poll['close'], 'U');

    $output = array(
        'ACTION' => 'Modify',
        'POLL_ID' => $poll['pollID'],
        'CATEGORY_ID' => $poll['categoryID'],
        'POLL_QUESTION' => Scrub::text($poll['question']),
        'OPTION_0' => Scrub::text($poll['option_0']),
        'OPTION_1' => Scrub::text($poll['option_1']),
        'OPTION_2' => Scrub::text($poll['option_2']),
        'OPTION_3' => Scrub::text($poll['option_3']),
        'OPTION_4' => Scrub::text($poll['option_4']),
        'OPTION_5' => Scrub::text($poll['option_5']),
        'OPTION_6' => Scrub::text($poll['option_6']),
        'OPTION_7' => Scrub::text($poll['option_7']),
        'OPTION_8' => Scrub::text($poll['option_8']),
        'OPTION_9' => Scrub::text($poll['option_9']),
        'CLOSE' => $closeDate->getLocalShort(),
        'FREQUENCY_ONCE_SELECTED' => '',
        'FREQUENCY_HOURLY_SELECTED' => '',
        'FREQUENCY_DAILY_SELECTED' => '',
        'FREQUENCY_' . strtoupper($poll['frequency']) . '_SELECTED' => 'selected="selected"',
    );

    $this->setVar($output);

    /* Render out breadcrumbs
     */
    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($poll['categoryID']);
    }
    catch (\Exception $e) {
        error(13);
    }

    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'Modify Poll',
            'href' => '',
            'class' => 'active',
        ]
    ]);

} else if (is_numeric($this->arg[1])) {

    // check permission
    if ($groupHandler->getPermission($this->arg[1]) < WRITE_ACCESS) {
        error(19);
    }

    $this->setFile(TEMPLATE_content, "poll/modify.html");
    $this->setTitle('Create Poll');

    $output = array(
        'ACTION' => 'Create',
        'POLL_ID' => '',
        'CATEGORY_ID' => $this->arg[1],
        'COMPONENT_HOMEPAGE_ID' => Scrub::text($this->arg[2]),
        'POLL_QUESTION' => '',
        'OPTION_0' => '',
        'OPTION_1' => '',
        'OPTION_2' => '',
        'OPTION_3' => '',
        'OPTION_4' => '',
        'OPTION_5' => '',
        'OPTION_6' => '',
        'OPTION_7' => '',
        'OPTION_8' => '',
        'OPTION_9' => '',
        'CLOSE' => Alaress_Date_Format::now()->addMonths(1)->getLocalShort(),
        'FREQUENCY_ONCE_SELECTED' => '',
        'FREQUENCY_HOURLY_SELECTED' => '',
        'FREQUENCY_DAILY_SELECTED' => '',
    );

    $this->setVar($output);

    /* Render out breadcrumbs
     */
    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($this->arg[1]);
    }
    catch (\Exception $e) {
        error(13);
    }

    $trail = new Breadcrumbs();
    $trail->renderIntoSitePage($this, 'BREADCRUMB', [
        [
            'text' => $folder->getName(),
            'href' => "/homepage/{$folder->getId()}",
            'class' => '',
        ],
        [
            'text' => 'New Poll',
            'href' => '',
            'class' => 'active',
        ]
    ]);
} else {
    error(2);
}
