<?php

use Schoolbox\Calendar\Event;
use Schoolbox\Permission\Access\Access as FolderAccess;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\UserMapper;
use Schoolbox\Calendar\Event\Access;

if (isset($this)) {
    $this->pageNotFound();
} else {
    /* Direct access to modify.php -_-
     */

    require_once __DIR__ . '/../../conf/core/require.php';
    $kernel = new Schoolbox\Application\WebRequest\Kernel();

    /** @var SitePage $page */
    $page = $kernel->getDependencyInjector()->get('sitePage');
}

$page->setFile(TEMPLATE_content, 'calendar/modify.html');
$page->setBlock(TEMPLATE_content, 'delete_button', 'delete_button_block');
$page->setBlock(TEMPLATE_content, 'recurring_dates', 'recurring_dates_block');
$page->setBlock(TEMPLATE_content, 'recurring_reminder', 'recurring_reminder_block');
$page->setBlock(TEMPLATE_content, 'event_group_drop', 'event_group_drop_block');
$page->setBlock(TEMPLATE_content, 'type_select', 'type_select_block');
$page->setBlock(TEMPLATE_content, 'type_no_select', 'type_no_select_block');

/** @var Schoolbox\User  $currentUser */
$currentUser = $_SESSION['user'];

if (isset($_GET['userId']) && is_numeric($_GET['userId'])) {
    try {
        $userMapper = new UserMapper();
        $selectedUser = $userMapper->findById($_GET['userId']);
    } catch (\Exception $e) {
        error(31);
    }
} else {
    $selectedUser = $currentUser;
}

/* Modify existing event
 */
if (is_numeric($_GET['eventID'])) {
    $mapper = new \Schoolbox\Calendar\EventMapper();

    try {
        $event = $mapper->findById($_GET['eventID']);
    } catch (\Exception $e) {
        error(160);
        exit;
    }

    /* Check user has permission to modify this event
     */
    $eventAccess = new Access(FolderAccess::getInstance());

    if ($eventAccess->checkExisting($currentUser, $event) == false) {
        error(34);
    }

    /* Default values
     */
    $defaultTypeId      = $event->getType()->getId();
    $defaultAccessLevel = $event->getAccess();

    /* Preselected campuses
     */
    $selectedCampuses = array();
    $allCampuses      = $event->getCampusCollection();

    foreach ($allCampuses as $currentCampus) {
        $selectedCampuses[] = $currentCampus->getId();
    }

    /* Preselected roles
     */
    $selectedRoles = array();
    $allRoles      = $event->getRoleCollection();

    foreach ($allRoles as $currentRole) {
        $selectedRoles[] = $currentRole->getRoleId();
    }

    /* Only provide publish date if one was provided and if it
     * wasn't useless somehow
     */
    if ($event->getPublishDateTime() && strlen($event->getPublishDateTime()->getDatabase())) {
        $publishDate = $event->getPublishDateTime()->getLocal();
    } else {
        $publishDate = '';
    }

    /* Miscellaneous setvarring
     * Hide advanced options by default
     */
    $page->setVar(
        array(
            'FUNCTION'       => 'Update',
            'SHOW_ADVANCED'  => 'none',
            'ADVANCED_SHOW'  => '',
            'ADVANCED_CLOSE' => 'hidden',
        )
    );

    $page->setVar(
        array(
            'EVENT_ID'             => $event->getId(),
            'EVENT_USER_ID'        => $event->getOwner()->getId(),
            'EVENT_AUTHOR_ID'      => $event->getAuthor()->getId(),
            'EVENT_TITLE'          => Scrub::text($event->getTitle()),
            'EVENT_ALLDAY_CHECKED' => $event->getAllDay() ? '1' : '0',
            'EVENT_TIMEINPUTS'     => $event->getAllDay() ? 'hide' : '',
            'EVENT_ATTENDANCE'     => $event->getAttendance() ? 'checked="checked"' : '',
            'EVENT_START_DATE'     => Scrub::text($event->getAllDay() ? $event->getStartDateTime()->getLocalShort() : $event->getStartDateTime()->getLocal()),
            'EVENT_END_DATE'       => Scrub::text($event->getAllDay() ? $event->getEndDateTime()->getLocalShort() : $event->getEndDateTime()->getLocal()),
            'EVENT_PUBLISH_DATE'   => Scrub::text($publishDate),
            'EVENT_LOCATION'       => Scrub::text($event->getLocation()),
            'EVENT_DETAIL'         => Scrub::text($event->getDetail()),
            'EVENT_REDIRECT'       => $event->setupRedirect(
                urldecode($_GET['returnTo']),
                $event->getStartDateTime()->getURLDate(),
                $_GET['homepageId'],
                $_GET['userId']
            ),
            'EVENT_RECURRENCE_COLLAPSE_STATE' => 'collapsed',
            'EVENT_RECURRENCE_COLLAPSE_CLASS' => 'hide',
            'RETURN_STR' => '&returnTo='.$_GET['returnTo'].
                ($_GET['userId']?"&userId=".$_GET['userId']:"").
                ($_GET['homepageId']?"&homepageId=".$_GET['homepageId']:"").
                ($_GET['returnDate']?"&returnDate=".$_GET['returnDate']:"")
        )
    );

    $page->parse('delete_button_block', 'delete_button');

    /* Recurrence
     */
    // Is there a recurrence? If so don't show the repeat selector.
    $recurrence = array();
    if ($event->getSeriesId()) {
        $eventRecurrenceMapper = new \Schoolbox\Calendar\EventRecurrenceMapper($mapper);
        $recurrence            = $eventRecurrenceMapper->getSeriesRecurrence($event);
    }
    if (!empty($recurrence)) {
        $page->setVar('EVENT_REC_FREQ', ucfirst($recurrence['freq']));
        $page->setVar('EVENT_REC_START', $recurrence['start']->getLongDateOnly());
        $page->setVar('EVENT_REC_END', $recurrence['end']->getLongDateOnly());
        $page->setVar('EVENT_RECURRENCE_COLLAPSE_STATE', 'uncollapsed');
        $page->setVar('EVENT_RECURRENCE_COLLAPSE_CLASS', '');
        $page->parse('recurring_reminder_block', 'recurring_reminder');
        $page->setVar('recurring_dates_block', '');
    } else {
        $page->setVar('recurring_reminder_block', '');
        $page->parse('recurring_dates_block', 'recurring_dates');
    }

} else {
    /* Create new event
     */
    /* Set up default dates
     */
    $event = new \Schoolbox\Calendar\Event();
    $event->setupDates($_GET['date']);
    if (!$event->getAllDay()) {
        $event->getEndDateTime()->addHours(1);
    }

    $defaultTypeId      = isset($_GET['type']) && is_numeric($_GET['type']) ? $_GET['type'] : '';
    $defaultAccessLevel = isset($_GET['level']) && is_numeric($_GET['level']) ? $_GET['level'] : '';

    /* Preselected campus
     */
    $selectedCampuses = array($_SESSION['campus'][0]);

    /* Preselected roles
     */
    $selectedRoles = array();

    /* Miscellaneous setvarring
     * Hide advanced options by default
     */
    $page->setVar(
        array(
            'FUNCTION'       => 'Add',
            'SHOW_ADVANCED'  => 'none',
            'ADVANCED_SHOW'  => '',
            'ADVANCED_CLOSE' => 'hidden',
        )
    );

    $page->setVar(
        array(
            'EVENT_ID'             => '',
            'EVENT_USER_ID'        => $selectedUser->getId(),
            'EVENT_AUTHOR_ID'      => $currentUser->getId(),
            'EVENT_TITLE'          => isset($_GET['title']) ? $_GET['title'] : '',
            'EVENT_ALLDAY_CHECKED' => $event->getAllDay() ? '1' : '0',
            'EVENT_TIMEINPUTS'     => $event->getAllDay() ? 'hide' : '',
            'EVENT_ATTENDANCE'     => '',
            'EVENT_START_DATE'     => Scrub::text($event->getAllDay() ? $event->getStartDateTime()->getLocalShort() : $event->getStartDateTime()->getLocal()),
            'EVENT_END_DATE'       => Scrub::text($event->getAllDay() ? $event->getEndDateTime()->getLocalShort() : $event->getEndDateTime()->getLocal()),
            'EVENT_PUBLISH_DATE'   => Alaress_Date_Format::now()->getLocal(),
            'EVENT_LOCATION'       => '',
            'EVENT_DETAIL'         => '',
            'EVENT_REDIRECT'       => $event->setupRedirect(
                $_GET['returnTo'],
                ($_GET['returnDate']?$_GET['returnDate']:$event->getStartDateTime()->getURLDate()),
                $_GET['homepageId'],
                $_GET['userId']
            ),
            'EVENT_RECURRENCE_COLLAPSE_STATE' => 'collapsed',
            'EVENT_RECURRENCE_COLLAPSE_CLASS' => 'hide',
        )
    );

    /* Recurrence
     */
    $page->setVar(
        array(
            'recurring_reminder_block' => '',
            'delete_button_block'      => ''
        )
    );

    $page->parse('recurring_dates_block', 'recurring_dates');
}

/* Event Types (except tasks)
 */
$calendarEventTypeMapper = new \Schoolbox\Calendar\EventTypeMapper();
$allEventTypes           = $calendarEventTypeMapper->findAll()->filter(
    function ($eventType) {
        /** @var \Schoolbox\Calendar\EventType $eventType */
        return !$eventType->isTaskType();
    }
);
$page->setVar('type_select_block', '');
$page->setVar('type_no_select_block', '');
$page->dropDown(
    'type_drop',
    $allEventTypes,
    $defaultTypeId,
    'type_select',
    array(
        'id'   => 'EVENT_EVENTTYPE_ID',
        'type' => 'EVENT_EVENTTYPE_NAME'
    )
);
// Is this a task? Don't show the event type
// (You shouldn't even be here if this is a task)
if ($event->getType()->isTaskType()) {
    $page->setVar('EVENT_EVENTTYPE_ID', $event->getType()->getId());
    $page->setVar('EVENT_EVENTTYPE_NAME', $event->getType()->getType());
    $page->parse('type_no_select_block', 'type_no_select');
} else {
    $page->parse('type_select_block', 'type_select');
}

/**
 * Group (Folder) selection
 */

/* Only provide Group option if groups have been defined
 */
$folderMapper = \Schoolbox\Resource\Folder\Mapper::getInstance();

/* Possible options, based on current user's involvement.
 */
$currentUserFolderIds = $folderMapper->findFolderIdsForUser($currentUser, true, UserAccess::ACCESS_WRITE);

/* Possible options, based on calendar user's involvement. Will intersect with current user
 */
$calendarUserFolderList = $folderMapper->findForUser($selectedUser, true, UserAccess::ACCESS_READ);

/* Ones that it's already in, from previous selections
 */
$alreadyInFolderList = $event->getCategoryCollection();

/* Get data about the Folders
 */
$tempFolderIds = array();

foreach ($alreadyInFolderList as $current) {
    $tempFolderIds[] = $current->getCategoryId();
}

/* If user has write access to, or is a group manager,
 * but isn't involved in the homepage used to get here:
 * force the homepage into the checkbox list
 */
if (isset($_GET['homepageId']) && !in_array($_GET['homepageId'], $tempFolderIds)) {
    if (FolderAccess::getInstance()->getFolderAccess($_SESSION['user'], (int)$_GET['homepageId']) >= UserAccess::ACCESS_WRITE) {
        $tempFolderIds[] = $_GET['homepageId'];
    }
}

$allFolders = $folderMapper->findByColumnList(array('id' => $tempFolderIds));

/**
 * Dedupe and make synonymous
 */

/* All the checkbox options, deduped, are here
 */
$allFolderList = $folderMapper->createCollection();

/* A simple stack of ids for checkboxes which should already be ticked
 */
$previousInvolvement = array();

/* Ones that you could possibly tick (intersect of write level of logged in user and read level of calendar user)
 */
foreach ($calendarUserFolderList as $current) {
    $currentId = $current->getId();
    if (in_array($currentId, $currentUserFolderIds, false) || $currentUser->isSuperuser() ) {
        $allFolderList[$currentId] = $current;
    }
}

/* Ones that have been ticked in the past
 */
foreach ($allFolders as $currentFolder) {
    if (!isset($allFolderList[$currentFolder->getId()])) {
        $allFolderList[$currentFolder->getId()] = $currentFolder;
    }

    $previousInvolvement[] = $currentFolder->getId();
}

/* Group checkbox array when using Group Event Level
 */
if(\count($allFolderList)) {

    // Order by title
    $allFolderList->sortByCallback(
        function(Schoolbox\Resource\Folder\Model $a, Schoolbox\Resource\Folder\Model $b) {
            return strnatcasecmp($a->getTitle(), $b->getTitle());
        }
    );

    foreach ($allFolderList as $currentFolder) {
        $folderId          = $currentFolder->getId();
        $alreadyInCategory = $event->getCategoryCollection()->getBy('categoryId', $folderId) || in_array($folderId, $previousInvolvement);
        $fromHomepage      = (($_GET['level'] == Event::ACCESS_GROUP) && ($_GET['homepageId'] == $folderId));

        /* If you have already chosen these categories, or if it's specified
         * from a homepage via Group Calendar > Add Event
         */
        if ($alreadyInCategory || $fromHomepage) {
            $selected = 'checked="checked"';
        } else {
            $selected = '';
        }

        $page->setVar(
            array(
                'EVENT_GROUP_ID'          => $folderId,
                'EVENT_GROUP_NAME'        => Scrub::text($currentFolder->getTitle()),
                'EVENT_GROUP_ID_SELECTED' => $selected
            )
        );

        $page->parse('event_group_drop_block', 'event_group_drop', true);
    }
} else {
    $page->setVar('event_group_drop_block', '');
}


/* Render all the dropdowns
 */

/* Only provide Campus options if campuses have been defined
 */
$campusMapper = \Schoolbox\CampusMapper::getInstance();
$campusList   = $campusMapper->getAvailableCampusesForUser($selectedUser);

/* Event Access Levels
 * Requires both $possibleFolderList and $campusList for optional values
 */
$page->dropDown(
    'event_level_drop',
    new \Schoolbox\Calendar\EventLevelCollection($allFolderList->getAll(), $campusList),
    $defaultAccessLevel,
    TEMPLATE_content,
    array(
        'id'   => 'EVENT_EVENTLEVEL_ID',
        'name' => 'EVENT_EVENTLEVEL_NAME',
    )
);

/* Campus Checkbox Array when using Campus Event Level
 */
if ($campusList->count()) {
    $allCampuses = array();

    foreach ($campusList as $current) {
        $allCampuses[] = array(
            'id'   => $current->getId(),
            'name' => $current->getName()
        );
    }

    $page->checkList(
        'event_campus_list',
        $allCampuses,
        $selectedCampuses,
        TEMPLATE_content,
        array(
            'id'   => 'EVENT_CAMPUS_ID',
            'name' => 'EVENT_CAMPUS_NAME',
        )
    );
} else {
    $page->setBlock(TEMPLATE_content, 'event_campus_list', 'event_campus_list_block');
    $page->setVar('event_campus_list_block', '');
}

/* Roles, when Campus Event Level is used
 */
$roleMapper = new \Schoolbox\RoleMapper();
$roleList   = $roleMapper->findAll();

/* Roles checkbox array when using Campus Event Level
 * This really needs to be updated - why is checkList behaviour different to
 * the dropDown one when it comes to collections?
 */
$allRoles = array();

foreach ($roleList as $current) {
    $allRoles[] = array(
        'id'   => $current->getId(),
        'name' => $current->getName()
    );
}

$page->checkList(
    'event_role_list',
    $allRoles,
    $selectedRoles,
    TEMPLATE_content,
    array(
        'id'   => 'EVENT_ROLE_ID',
        'name' => 'EVENT_ROLE_NAME'
    )
);

/* Pass Personal const for ui elements
 */
$page->setVar([
    'PERSONAL_LEVEL' => Event::ACCESS_PERSONAL
]);

$page->outputPage();
