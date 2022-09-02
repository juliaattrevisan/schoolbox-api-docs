<?php

use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Storage\FilesystemProxy;

SitePage::extendExecutionTime();

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */
$this->setTitle('Admin - Bulk Add Calendar Events');
$this->setFile(TEMPLATE_content, 'adminv2/bulkAddCalendar.html');
$this->setBlock(TEMPLATE_content, 'event', 'event_block');

// include the admin navigation
(new Subnav($this->getDi()->get('config')))
    ->generatePhplib($this, '/adminv2/calendar/Import')
    ->generateBreadcrumbsHtml($this, '/adminv2/calendar/Import');

/* Two types of upload.. either single user.. or via csv file..
 */

/** @var FilesystemProxy $filesystemProxy */
$filesystemProxy = $this->getDi()->get('filesystem.storageProxy');

$collector = (new CollectorFactory())->build($_POST['bulkadd'] ?? []);
try {
    $file = $collector->getFirst();
} catch (\Schoolbox\Resource\Exception $e) {
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}
if (isset($_POST['userGroup']) && isset($_POST['campusList'])) {
    $extension = $file->getExtension();
    /* Must have the correct .csv or .ics extension
     */
    if (!('csv' === $extension || 'ics' === $extension)) {
        $this->error(\Schoolbox\Error\Mapper::ERROR_CALENDAR_IMPORT_FILE_REQUIRED);
    }

    if (isset($_POST['userid'])) {
        $ownerId = $_POST['userid'];
    } else {
        $ownerId = null;
    }

    $bulkHandler = new Bulk_Calendar($_FILES['bulkadd']['tmp_name'], null, $ownerId, [], $_POST['userGroup'], $_POST['campusList']);
    $allEvents = $bulkHandler->addCalendar();

    if ($allEvents && count($allEvents)) {
        foreach ($allEvents as $currentEvent) {
            $this->setVar([
                'EVENT_NAME' => $currentEvent['name'],
                'EVENT_STATUS' => $currentEvent['status'],
                'EVENT_ERROR' => $currentEvent['error'],
            ]);

            $this->parse('event_block', 'event', true);
        }
    } else {
        $this->setVar('event_block', '');
    }
} else {
    $this->error(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
}
