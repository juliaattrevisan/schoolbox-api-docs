<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Breadcrumbs;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Storage;
use Schoolbox\Resource\Storage\FilesystemProxy;

// not in SitePage context?
if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}
/** @var SitePage $this */

SitePage::extendExecutionTime();

$groupHandler = new Group();

if ($groupHandler->getPermission($_POST['homepageId']) < UserAccess::ACCESS_WRITE) {
    $this->error(\Schoolbox\Error\Mapper::ERROR_PERM_PAGE_VIEW);
} else {
    $user = $_SESSION['user'];
    $author = $_SESSION['user'];
    $categoryList = array($_POST['homepageId']);
    $groupList = array();
    $campusList = array();

    try {
        $folderMapper = new FolderMapper();
        $folder = $folderMapper->findById($_POST['homepageId']);
    } catch (\Schoolbox\Mapper\Exception $e) {
        echo $e->getMessage();
        $this->error(\Schoolbox\Error\Mapper::ERROR_CATEGORY_DELETED);
    }

    /* A file is mandatory
     */
    if (isset($_POST['attachment']) && null !== $_POST['attachment']) {
        $factory = new CollectorFactory();

        try {
            /** @var Storage\Model $result */
            $result = $factory->build($_POST['attachment'])->getFirst();
        } catch (\Schoolbox\Resource\Exception $e) {
            $this->error(\Schoolbox\Error\Mapper::ERROR_FILE_UPLOAD_INCOMPLETE);
        }

        $extension = strtolower($result->getExtension());
        if (!('csv' === $extension || 'ics' === $extension)) {
            $this->error(\Schoolbox\Error\Mapper::ERROR_CALENDAR_IMPORT_FILE_REQUIRED);
        }

        // Ensure the file is available locally
        /** @var FilesystemProxy $filesystemProxy */
        $filesystemProxy = $this->getDi()->get('filesystem.storageProxy');
        $file            = sb_tempnam('cal');
        $fp              = fopen($file, 'wb');
        stream_copy_to_stream($filesystemProxy->readStream($result), $fp);
        fclose($fp);

        $factory = new \Calendar\Import\Factory($user, $author, $categoryList, $groupList, $campusList, \Schoolbox\Calendar\Event::ACCESS_GROUP, $file);
        $strategy = $factory->build();
        $strategy->load();

        $allEvents = $strategy->getOutput();

        $this->setFile(TEMPLATE_content, 'calendar/importForm.html');
        $this->setBlock(TEMPLATE_content, 'event', 'event_block');

        if ($allEvents && count($allEvents)) {
            foreach ($allEvents as $currentEvent) {
                $this->setVar(
                    array(
                        'EVENT_NAME' => $currentEvent['name'],
                        'EVENT_STATUS' => $currentEvent['status'],
                        'EVENT_ERROR' => $currentEvent['error'],
                    )
                );

                $this->parse('event_block', 'event', true);
            }
        } else {
            $this->setVar('event_block', '');
        }

        $trail = new Breadcrumbs();
        $trail->renderIntoSitePage($this, 'BREADCRUMB', [
            [
                'text' => $folder->getName(),
                'href' => "/homepage/{$folder->getId()}",
                'class' => '',
            ],
            [
                'text' => 'Import Calendar',
                'href' => '',
                'class' => 'active',
            ]
        ]);

        $this->setVar(
            array(
                'HOMEPAGE_ID' => $folder->getId(),
                'HOMEPAGE_NAME' => $folder->getName()
            )
        );
    } else {
        $this->error(\Schoolbox\Error\Mapper::ERROR_FILE_UPLOAD_INCOMPLETE);
    }
}
