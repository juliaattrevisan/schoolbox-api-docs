<?php
/**
 * Render the javascript-based Fullcalendar implementation
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Api\ModuleChecker;
use Calendar\Event\Collection\Lister;
use Calendar\Event\Type\EventBase;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Config\Loader as ConfigLoader;

$userMapper = new \Schoolbox\UserMapper();

// If three args were provided, assume userId and two dates respectively
// Otherwise just assume currently logged in user

$user = null;

/** @var SitePage $this */
if (count($this->arg) >= 2) {

    // Viewing someone else's calendar:
    // Url format: /calendar/list/{userId}/{startDate}/{finishDate}

    // Check that the user exists

    try {
        $user = $userMapper->findById($this->arg[0]);
        Calendar_Layout_Full_Javascript::permissionCheck($this->arg[0], $_SESSION['user'], new Group());
    } catch (Exception $e) {
        error($e->getCode());
    }

    // Take them on a date

    $start = Alaress_Date_Format::newUrlDate($this->arg[1]);

    if (isset($this->arg[2])) {
        $finish = Alaress_Date_Format::newUrlDate($this->arg[2]);
    } else {
        $finish = clone $start;
        $finish->addMonths(1);
    }

} else {

    // Viewing your own calendar
    // Url format: /calendar/list/{startDate}

    $user = $_SESSION['user'];

    // Take them on a date

    $start = Alaress_Date_Format::newUrlDate($this->arg[0]);
    $finish = clone $start;
    $finish->addMonths(1);
}


$this->setFile(TEMPLATE_content, 'calendar/list.html');
$this->setBlock(TEMPLATE_content, 'has_no_events', 'has_no_events_block');
$this->setBlock(TEMPLATE_content, 'date', 'date_block');
$this->setBlock(TEMPLATE_content, 'has_series', 'has_series_block');
$this->setBlock('date', 'event', 'event_block');

$this->setVar(
    array(
        'has_no_events_block' => '',
        'date_block' => '',
        'event_block' => '',
        'has_series_block' => '',
    )
);

$this->setVar(
    array(
        'CALENDAR_USER_ID' => $user->getId(),
        'CALENDAR_USER_TOKEN' => $user->getKey(),
        'CALENDAR_USER_FULLNAME' => $user->getFullname(),
        'CALENDAR_PARAMS' => "{$start->getURLDate()}/{$user->getId()}",
        'SELECTED_START' => $start->getLocalShort(),
        'SELECTED_FINISH' => $finish->getLocalShort(),
    )
);

/** @var Calendar_Api_Factory $factory */
$factory = $this->getDi()->get('calendarApiFactory');
$api = $factory->produceForUser($user, $user);

// Extend finish by one day for search

$calendar = new Lister(
    $api,
    $start->getUnixTimeStamp(),
    $finish->addDays(1)->getUnixTimeStamp(),
    $user,
    $this->getDi(),
    $user
);

$lastDate = false;

if ($calendar->getAllEvents()->count()) {
    $hasSeries = false;
    foreach ($calendar->getAllEvents() as $currentEvent) {
        $currentDate = $currentEvent->getStartTime()->getDatabaseDate();

        if ($lastDate && ($lastDate != $currentDate)) {
            $this->parse('date_block', 'date', true);
            $this->setVar('event_block', '');
        }

        $this->setVar('EVENT_DATE', $currentEvent->getStartTime()->getLongDateOnly());

        $htmlEvent = $calendar->renderEvent($currentEvent, EventBase::RENDER_FORMAT_HTML);
        if ($htmlEvent) {
            $this->setVar('TWIG_EVENT', $htmlEvent);
            $this->parse('event_block', 'event', true);
        }
        $lastDate = $currentDate;

        if ($currentEvent->getSeriesId()
            || ($currentEvent instanceof \Calendar\Event\Type\ResourceBooking\Event
                && $currentEvent->getBooking()->getEvent()->getSeriesId())) {
            $hasSeries = true;
        }
    }

    // Parze out blox after last event

    $this->parse('date_block', 'date', true);
    if ($hasSeries) {
        $this->parse('has_series_block', 'has_series', true);
    }
} else {
    $this->parse('has_no_events_block', 'has_no_events');
}

$groupHandler = new Group();
$category_list = $groupHandler->involvedInCategoryList($user->getId());

$calendarHandler = new Calendar();
$campus_list = $calendarHandler->getFilterCampusList($user->getId());

// Render everything else out

// Load sources

$moduleChecker = new ModuleChecker(ConfigLoader::getInstance());

// This will be broken now getSourceListForCalendar returns ids as a list.
// Doesn't matter, this is going away.
$sourceList = Calendar_Api_Config_Mapper::getInstance()->getSourceListForCalendar($moduleChecker);

// Select all types

$typeList = EventTypeMapper::getInstance()->findAll();

if (isset($_SESSION['calendarFilter'])) {

    $selected['group'] = $_SESSION['calendarFilter']['groups'];
    $selected['campus'] = $_SESSION['calendarFilter']['campus'];
    $selected['event_type'] = $_SESSION['calendarFilter']['events'];
    $selected['source'] = $_SESSION['calendarFilter']['sources'];

} else {

    // Select all groups

    if (count($category_list)) {
        foreach ($category_list as $cat) {
            $selected['group'][] = $cat['id'];
        }
    }

    // Select all campuses

    $selected['campus'] = $_SESSION['campus'];

    foreach ($typeList as $type) {
        $selected['event_type'][] = $type->getId();
    }

    // Select all sources

    foreach ($sourceList as $source) {
        $selected['source'][] = $source['id'];
    }
}

$this->dropDown(
    'export_group',
    $category_list,
    array(),
    TEMPLATE_content,
    array('name' => 'EXPORT_GROUP_NAME', 'id' => 'EXPORT_GROUP_ID')
);

$this->dropDown(
    'export_campus',
    $campus_list,
    array(),
    TEMPLATE_content,
    array('CAMPUS_NAME' => 'EXPORT_CAMPUS_NAME', 'CAMPUS_ID' => 'EXPORT_CAMPUS_ID')
);

$this->dropDown(
    'export_type',
    $typeList,
    array(),
    TEMPLATE_content,
    array('type' => 'EXPORT_TYPE_NAME', 'id' => 'EXPORT_TYPE_ID')
);

//Bastardise the folder names to get the codes in
//Better than changing checkList()
foreach ($category_list as $key => $value) {
    if (!empty($category_list[$key]['code'])) {
        $category_list[$key]['name'] = $category_list[$key]['name'] . ' (' . $category_list[$key]['code'] . ')';
    }
}
$this->checkList(
    'filter_group',
    $category_list,
    $selected['group'],
    TEMPLATE_content,
    array('name' => 'GROUP_NAME', 'id' => 'GROUP_ID')
);

// Might need special PHPLibs here

$this->checkList(
    'filter_source',
    $sourceList,
    $selected['source'],
    TEMPLATE_content,
    array('name' => 'SOURCE_NAME', 'id' => 'SOURCE_ID')
);

$this->checkList(
    'filter_campus',
    $campus_list,
    $selected['campus'],
    TEMPLATE_content,
    array('name' => 'CAMPUS_NAME', 'id' => 'CAMPUS_ID')
);

$this->checkList(
    'filter_event_type',
    $typeList,
    $selected['event_type'],
    TEMPLATE_content,
    array(
        'type' => 'EVENT_TYPE_NAME',
        'id' => 'EVENT_TYPE_ID',
        'color' => 'EVENT_TYPE_COLOUR'
    )
);
