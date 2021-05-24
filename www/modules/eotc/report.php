<?php

/**
 * EOTC event report
 *
 * A unified report that allows searching for all events
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use EOTC\EventStatusMapper;

/** @var SitePage $this */

/* Only EOTC Administrators can view this page
 */
$authenticator = new \EOTC\Authenticator($this);

/** @var \Schoolbox\User $sessionUser */
$sessionUser = $_SESSION['user'];

$this->setFile(TEMPLATE_content, 'modules/eotc/report.html');
$this->setBlock(TEMPLATE_content, 'report_timespan', 'report_timespan_block');
$this->setBlock(TEMPLATE_content, 'report_status', 'report_status_block');
$this->setBlock(TEMPLATE_content, 'event_list_group', 'event_list_group_block');
$this->setBlock('event_list_group', 'event', 'event_block');
$this->setBlock('event', 'link_start', 'link_start_block');
$this->setBlock('event', 'link_end', 'link_end_block');

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
$dateFrom     = '';
$dateTo       = '';
$statuses     = isset($_GET['status'])
    ? $_GET['status']
    : [
        EventStatusMapper::STATUS_DRAFT,
        EventStatusMapper::STATUS_INPRINCIPLE,
        EventStatusMapper::STATUS_DETAILSCOMPLETED,
        EventStatusMapper::STATUS_AWAITINGFINALAPPROVAL,
    ];
$statusList   = EventStatusMapper::getInstance()->findAll();

// get from/to date out
$dateFrom = (isset($this->arg[1]) && $this->arg[1] != '')
    ? new Alaress_Date_Format($this->arg[1], Alaress_Date_Format::URL_FORMAT)
    : null;
$dateTo   = (isset($this->arg[2]) && $this->arg[2] != '')
    ? new Alaress_Date_Format($this->arg[2], Alaress_Date_Format::URL_FORMAT)
    : null;

// No dates at all: set defaults. 'From' will use the review default, 'to' will be blank
if (!isset($dateFrom) && !isset($dateTo)) {
    $dateFrom = Alaress_Date_Format::now()->modify(EventStatusMapper::DEFAULT_FROM_DATE_FOR_REVIEW);
}

// To can't be before from
if (isset($dateFrom, $dateTo) && $dateTo->lt($dateFrom)) {
    $dateTo = clone $dateFrom;
}

if ($timespanType === 'range') {
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
            if (
                $authenticator->isEOTCAdministratorForCampus(
                    $sessionUser,
                    $currentEvent
                ) || $sessionUser->isSuperuser()
            ) {
                $this->setVar([
                    'EVENT_ID'                         => $currentEvent->getId(),
                    'EVENT_STATUS_NAME'                => $currentEvent->getMetadata()->getStatus()->getName(),
                    'EVENT_TITLE'                      => $currentEvent->getTitle(),
                    'EVENT_START_DATETIME'             => $currentEvent->getStartDatetime()->getShort(),
                    'EVENT_TEACHER_IN_CHARGE_ID'       => $currentEvent->getTeacherInCharge()->getId(),
                    'EVENT_TEACHER_IN_CHARGE_FULLNAME' => $currentEvent->getTeacherInCharge()->getFullName(),
                    'EVENT_CREATED_AT'                 => $currentEvent->getMetadata()->getCreatedAt()->getShort(),
                    'EVENT_STUDENTS'                   => $currentEvent->getMetadata()->getStudentAttendees()->count(),
                    'EVENT_RAMS_REQUIRED'              => $currentEvent->getMetadata()->getRamsRequired() ? 'Y' : 'N',
                ]);

                if ($currentEvent->getTeacherInCharge()->isDeleted()) {
                    $this->setVar('link_start_block', '');
                    $this->setVar('link_end_block', '');
                } else {
                    $this->parse('link_start_block', 'link_start');
                    $this->parse('link_end_block', 'link_end');
                }

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
