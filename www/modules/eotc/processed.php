<?php
/**
 * Processed
 *
 * Show approved/cancelled events that are at most 1 month old
 * -or- starting in the future
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use EOTC\EventStatusMapper;

/** @var SitePage $this */

/* Only EOTC Administrators can view this page
 */
$authenticator = new \EOTC\Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

$this->setFile(TEMPLATE_content, 'modules/eotc/processed.html');
$this->setBlock(TEMPLATE_content, 'report_timespan', 'report_timespan_block');
$this->setBlock(TEMPLATE_content, 'report_status', 'report_status_block');
$this->setBlock(TEMPLATE_content, 'event_list_group', 'event_list_group_block');
$this->setBlock('event_list_group', 'event', 'event_block');

$this->setVar([
    'event_list_group_block' => '',
]);

$eventMapper = \EOTC\EventMapper::getInstance();

/*
 * Report types:
 * /range/{from}/{to}: show a list of approved, rejected events between from, to
 * /:                  /range/{-1 month}/{now}
 * /all:               show all future events that are approved/rejected
 */
$timespanType = isset($this->arg[0]) ? $this->arg[0] : 'range';
$dateFrom = '';
$dateTo = '';
$statuses = isset($_GET['status'])
    ? $_GET['status']
    : [EventStatusMapper::STATUS_APPROVED, EventStatusMapper::STATUS_REJECTED,]
;
$statusList = EventStatusMapper::getInstance()->findAll();
if ($timespanType === 'range') {
    // get from/to date out
    $dateFrom = isset($this->arg[1])
        ? new Alaress_Date_Format($this->arg[1], Alaress_Date_Format::URL_FORMAT)
        : Alaress_Date_Format::now()->addMonths(-1);
    $dateTo = isset($this->arg[2])
        ? new Alaress_Date_Format($this->arg[2], Alaress_Date_Format::URL_FORMAT)
        : null;

    /** @var \EOTC\ListGroup\Collection $allListGroups */
    $allListGroups = $eventMapper->findAllByListGroups(
        $statuses,
        $dateFrom,
        $dateTo
    );
} elseif ($timespanType === 'all') {

    /* Show paginated events, ordered by datetime, till the
     * beginning of history
     */
    /** @var \EOTC\ListGroup\Collection $allListGroups */
    $allListGroups = $eventMapper->findAllByListGroups(
        $statuses,
        null
    );
}

// Output blocks
$options = ['range' => 'Date Range', 'all' => 'All'];
$this->dropDown(
    'report_timespan',
    [
        ['id' => 'range', 'type' => 'Date Range'],
        ['id' => 'all', 'type' => 'All'],
    ],
    $timespanType,
    TEMPLATE_content,
    [
        'id'   => 'REPORT_TIMESPAN_ID',
        'type' => 'REPORT_TIMESPAN_NAME',
    ]
);
$this->checkList(
    'report_status',
    $statusList,
    $statuses,
    TEMPLATE_content,
    [
        'id'   => 'REPORT_STATUS_ID',
        'name' => 'REPORT_STATUS_NAME',
    ]
);
$this->setVar('CLASS_REPORT_DATE', $timespanType === 'all' ? 'hide' : '');
$this->setVar('REPORT_DATE_START', ($dateFrom instanceof Alaress_Date_Format ? $dateFrom->getLocalShort() : ''));
$this->setVar('REPORT_DATE_END', ($dateTo instanceof Alaress_Date_Format ? $dateTo->getLocalShort() : ''));

/* Using foreach() won't increment the $position, which I use to get the name
 * of the collection currently being iterated
 */

/** @var Schoolbox\L10n\RosettaStone $l10n */
$l10n = $this->getDi()->get('l10n');

foreach ($allListGroups as $key => $currentListGroup) {
    if ($currentListGroup->count()) {
        $this->setVar([
            'EVENT_LIST_GROUP_NAME' => $l10n->translateSentence($allListGroups->getCurrentName($key)),
        ]);

        $i = 0;

        /** @var \EOTC\Event $currentEvent */
        foreach ($currentListGroup as $currentEvent) {
            if ($authenticator->isEOTCAdministratorForCampus($sessionUser,
                    $currentEvent) || $sessionUser->isSuperuser()) {
                $this->setVar([
                    'EVENT_ID'                         => $currentEvent->getId(),
                    'EVENT_STATUS_NAME'                => $currentEvent->getMetadata()->getStatus()->getName(),
                    'EVENT_TITLE'                      => $currentEvent->getTitle(),
                    'EVENT_START_DATETIME'             => $currentEvent->getStartDatetime()->getShort(),
                    'EVENT_TEACHER_IN_CHARGE_ID'       => $currentEvent->getTeacherInCharge()->getId(),
                    'EVENT_TEACHER_IN_CHARGE_FULLNAME' => $currentEvent->getTeacherInCharge()->getFullname(),
                    'EVENT_CREATED_AT'                 => $currentEvent->getMetadata()->getCreatedAt()->getShort(),
                    'EVENT_STUDENTS'                   => $currentEvent->getMetadata()->getStudentAttendees()->count(),
                    'EVENT_RAMS_REQUIRED'              => $currentEvent->getMetadata()->getRamsRequired() ? 'Y' : 'N',
                ]);

                $this->parse('event_block', 'event', true);

                $i++;
            }
        }

        /* Only parse if an event you can manipulate was found
         */
        if ($i) {
            $this->parse('event_list_group_block', 'event_list_group', true);
        }

        /* Clear children after parsing out the parent
         */
        $this->setVar([
            'event_block' => '',
        ]);
    }
}
