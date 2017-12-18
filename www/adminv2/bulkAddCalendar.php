<?php
SitePage::extendExecutionTime(300);

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

$this->setTitle('Admin - Bulk Add Calendar Events');
$this->setFile(TEMPLATE_content, 'adminv2/bulkAddCalendar.html');
$this->setBlock(TEMPLATE_content, 'event', 'event_block');

// include the admin navigation
$nav = new AdminV2_Nav();
$nav->parse($this, 'calendar');

/* Two types of upload.. either single user.. or via csv file..
 */
if (isset($_FILES['bulkadd']['tmp_name']) && $_FILES['bulkadd']['tmp_name']) {

    if (isset($_POST['userid'])) {
        $ownerId = $_POST['userid'];
    } else {
        $ownerId = null;
    }

    if (isset($_POST['userGroup'])) {
        $groupList = $_POST['userGroup'];
    } else {
        $groupList = null;
    }

    if (isset($_POST['campusList'])) {
        $campusList = $_POST['campusList'];
    } else {
        $campusList = null;
    }

    $bulkHandler = new Bulk_Calendar($_FILES['bulkadd']['tmp_name'], null, $ownerId, null, $groupList, $campusList);
    $allEvents = $bulkHandler->addCalendar();

    if ($allEvents && count($allEvents)) {
        foreach ($allEvents as $currentEvent) {
            $this->setVar(array(
                'EVENT_NAME' => $currentEvent['name'],
                'EVENT_STATUS' => $currentEvent['status'],
                'EVENT_ERROR' => $currentEvent['error'],
            ));

            $this->parse('event_block', 'event', true);
        }
    } else {
        $this->setVar('event_block', '');
    }
} else {
    error(2);
}
