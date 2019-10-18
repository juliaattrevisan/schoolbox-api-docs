<?php

use Calendar\Api\ApiHierarchyGenerator;
use Calendar\Api\ModuleChecker;
use Schoolbox\Calendar\Relation;
use Schoolbox\Config\ConfigMapper;

if (!$_SESSION['user']->isSuperuser()) {
    error(19);
}

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'adminv2/calendar.html');
$this->setTitle('Admin - Calendar');

// include the admin navigation
$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'calendar');

$userHandler = new User();

$this->checkList(
    'campus_check_list',
    $userHandler->getCampusList(),
    '',
    TEMPLATE_content,
    [
        'id' => 'CAMPUSID',
        'name' => 'CAMPUSNAME'
    ]
);
$this->checkList(
    'user_group_check_list',
    $userHandler->getGroupList(),
    '',
    TEMPLATE_content,
    [
        'id' => 'USERGROUPID',
        'name' => 'USERGROUPNAME'
    ]
);

/* Stolen from Settings
 */
$this->setBlock(TEMPLATE_content, 'setting_tab', 'setting_tab_block');

$this->setBlock(TEMPLATE_content, 'import', 'import_block');

$this->setBlock(TEMPLATE_content, 'subscribe', 'subscribe_block');
$this->setBlock('subscribe', 'eventType', 'eventType_block');
$this->setBlock('eventType', 'connector', 'connector_block');
$this->setBlock('connector', 'connectorConfig', 'connectorConfig_block');
$this->setBlock('connector', 'has_multiple_options', 'has_multiple_options_block');

$this->setBlock(TEMPLATE_content, 'cleanup', 'cleanup_block');
$this->setBlock('cleanup', 'cleanup_has_no_events', 'cleanup_has_no_events_block');
$this->setBlock('cleanup', 'cleanup_has_events', 'cleanup_has_events_block');
$this->setBlock('cleanup_has_events', 'cleanup_event', 'cleanup_event_block');
$this->setBlock('cleanup_event', 'has_event_author', 'has_event_author_block');
$this->setBlock('cleanup_event', 'no_event_author', 'no_event_author_block');

$this->setVar('import_block', '');
$this->setVar('subscribe_block', '');
$this->setVar('eventType_block', '');
$this->setVar('connector_block', '');
$this->setVar('cleanup_block', '');
$this->setVar('cleanup_has_events_block', '');
$this->setVar('cleanup_has_no_events_block', '');
$this->setVar('cleanup_event_block', '');

if ($this->arg[0]) {
    $selectedType = urldecode($this->arg[0]);
} else {
    $selectedType = 'Import';
}

$typeList = [
    ['type' => 'Import'],
    ['type' => 'Subscribe'],
    ['type' => 'Cleanup']
];


foreach ($typeList as $type) {

    /* Navigation stuff
     */
    $this->setVar([
        'SETTING_TAB_NAME' => $type['type'],
        'SETTING_TAB_URL' => urlencode($type['type']),
        'SETTING_TAB_CURRENT' => '',
    ]);

    if ($selectedType == $type['type']) {
        $this->setVar('SETTING_TAB_CURRENT', 'active');
    }

    $this->parse('setting_tab_block', 'setting_tab', true);

    /* Individual pages
     * Import page
     */
    if (($type['type'] == 'Import') && ($selectedType == 'Import')) {
        $this->parse('import_block', 'import');
    }
    elseif (($type['type'] == 'Subscribe') && ($selectedType == 'Subscribe')) {

        /* Subscribe page
         */
        $moduleChecker = new ModuleChecker(ConfigMapper::getInstance());
        $generator     = new ApiHierarchyGenerator($moduleChecker);
        $availableApi  = $generator->generateApiHierarchy();

        /* All calendar subscription options go here
         */
        foreach ($availableApi as $currentEventType => $allConnectors) {

            /* Render titles etc
             * Put a space between 'CamelCase' names so they become 'Camel Case'
             */
            $this->setVar([
                'EVENTTYPE_LABEL' => trim(preg_replace('/[A-Z]+/', ' $0', $currentEventType)),
                'EVENTTYPE_NAME' => $currentEventType,
            ]);

            /* Inject individual templates just like before -_-
             */
            /** @var \Calendar_Api_Config_Connector_Abstract $stubInstance */
            foreach ($allConnectors as $currentConnector => $stubInstance) {

                /* Render titles etc
                 *
                 * Some kind of rendering issue exists where I can't combine it with the other
                 * setVar() further down. It leads to the wrong connector name being used.
                 */
                $this->setVar([
                    'CONNECTOR_NAME' => $stubInstance->getDefaultLabel(),
                    'CONNECTOR_CLASS' => $stubInstance->getConnectorClass(),
                    'CONNECTOR_ICON' => $currentConnector == 'Schoolbox' ? 'schoolbox' : 'calendar'
                ]);

                /* Process each config for this kind of connector
                 */
                $allConfigs = Calendar_Api_Config_Mapper::getInstance()->getAllConfigs(
                    $currentEventType,
                    $currentConnector,
                    $moduleChecker
                );

                if (count($allConfigs)) {
                    foreach ($allConfigs as $currentConfig) {
                        $this->setFile('configFragment', "adminv2/calendar/config/{$currentConnector}.html");
                        $this->setBlock('configFragment', 'event_type_list', 'event_type_list_block');
                        $this->setBlock('event_type_list', 'luEventType', 'luEventType_block');
                        $this->setBlock('configFragment', 'campus_list', 'campus_list_block');
                        $this->setBlock('configFragment', 'group_list', 'group_list_block');

                        /* Render individual connector inputs
                         */
                        $currentConfig->render($this);

                        /* Parse connector row
                         */
                        $this->parse('connectorConfig_block', 'configFragment', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->setVar('luEventType_block', '');
                        $this->setVar('campus_list_block', '');
                        $this->setVar('group_list_block', '');
                        $this->setVar('event_type_list_block', '');
                    }

                    /* Whether this connector displays at Ticked or not
                     */
                    $connectorActive = 'connector-active';
                    $connectorChecked = 'checked="checked"';
                } else {
                    $connectorActive = '';
                    $connectorChecked = '';
                }

                /* Render checkbox setting
                 */
                $this->setVar([
                    'CONNECTOR_CHECKED' => $connectorChecked,
                    'CONNECTOR_ACTIVE' => $connectorActive,
                ]);

                $this->parse('connector_block', 'connector', true);

                /* Clear children after parsing out the parent
                 */
                $this->setVar('connectorConfig_block', '');
                $this->setVar('configFragment', '');
                $this->setVar('has_multiple_options_block', '');
            }

            $this->parse('eventType_block', 'eventType', true);

            /* Clear children after parsing out the parent
             */
            $this->setVar('connector_block', '');
        }

        $this->parse('subscribe_block', 'subscribe');
    } /* Cleanup page
     */
    elseif (($type['type'] == 'Cleanup') && ($selectedType == 'Cleanup')) {

        /* If both dates specified, find events between those two dates inclusive
         */
        if (isset($_GET['cleanupFrom']) && isset($_GET['cleanupTo'])) {
            $from = \Alaress_Date_Format::newLocalShort($_GET['cleanupFrom']);
            $to = \Alaress_Date_Format::newLocalShort($_GET['cleanupTo']);
        } else {
            $from = new \Alaress_Date_Format(strtotime('-1 month'), 'U');
            $to = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
        }

        $this->setVar([
            'CLEANUP_HAS_DATES_FROM' => $from->getLocalShort(),
            'CLEANUP_HAS_DATES_TO' => $to->getLocalShort(),
            'has_event_author_block' => '',
            'no_event_author_block' => '',
        ]);

        $eventMapper = new \Schoolbox\Calendar\EventMapper();
        $allEvents = $eventMapper->findEventsBetween($from->getDatabaseDate(), $to->getDatabaseDate(), true);

        /** @var \Schoolbox\Calendar\Event $currentEvent */
        foreach ($allEvents as $currentEvent) {

            /* Auxiliary collections
             */
            $allUsers = [];

            /** @var Relation\Personal $currentUser */
            foreach ($currentEvent->getUserCollection() as $currentUser) {
                $user = \Schoolbox\UserMapper::getInstance()->findByIdWithFallback($currentUser->getUserId());

                if ($user instanceof \Schoolbox\User) {
                    $allUsers[] = $user->getFullName();
                }
            }

            $allCategories = [];

            /** @var \Schoolbox\Calendar\Relation\Category $currentCategory */
            foreach ($currentEvent->getCategoryCollection() as $currentCategory) {
                $allCategories[] = \Schoolbox\Resource\Folder\Mapper::getInstance()->findByIdWithFallback($currentCategory->getCategoryId())->getTitle();
            }

            $allCampuses = [];

            /** @var \Schoolbox\Campus $currentCampus */
            foreach ($currentEvent->getCampusCollection() as $currentCampus) {
                $allCampuses[] = $currentCampus->getName();
            }

            /* Combine all three of the auxiliary collections
             */
            $appliesTo = [];
            $appliesTo = array_merge($allUsers, $allCategories, $allCampuses);
            $appliesTo = implode('<br />', $appliesTo);

            /* Special selection modifiers for javascript control
             */
            $modifier = '';

            if ($currentEvent->isPersonalLevel()) {
                $modifier = 'personal';
            } elseif ($currentEvent->isGroupLevel()) {
                $modifier = 'group';
            } elseif ($currentEvent->isCampusLevel()) {
                $modifier = 'school';
            }

            $this->setVar([
                'CLEANUP_EVENT_ID' => $currentEvent->getId(),
                'CLEANUP_EVENT_TITLE' => $currentEvent->getTitle(),
                'CLEANUP_EVENT_START' => $currentEvent->getStartDateTime()->getShort(),
                'CLEANUP_EVENT_END' => $currentEvent->getEndDateTime()->getShort(),
                'CLEANUP_EVENT_EVENTTYPE' => $currentEvent->getType()->getType(),
                'CLEANUP_EVENT_APPLIES_TO' => $appliesTo,
                'CLEANUP_EVENT_MODIFIER' => $modifier,
            ]);

            /* Event author can now return as null when it doesn't exist anymore
             */
            if ($currentEvent->getAuthor() instanceof \Schoolbox\User) {
                $this->setVar([
                    'CLEANUP_EVENT_AUTHOR_ID' => $currentEvent->getAuthor()->getId(),
                    'CLEANUP_EVENT_AUTHOR_NAME' => $currentEvent->getAuthor()->getFullName(),
                ]);

                $this->parse('has_event_author_block', 'has_event_author');
            }
            else {
                $this->setVar([
                    'CLEANUP_EVENT_AUTHOR_ID' => '',
                    'CLEANUP_EVENT_AUTHOR_NAME' => 'Unknown User',
                ]);

                $this->parse('no_event_author_block', 'no_event_author');
            }

            $this->parse('cleanup_event_block', 'cleanup_event', true);

            /* Clear children after parsing out the parent
             */
            $this->setVar([
                'has_event_author_block' => '',
                'no_event_author_block' => '',
            ]);
        }

        if ($allEvents->count()) {
            $this->parse('cleanup_has_events_block', 'cleanup_has_events');

            $this->setVar([
                'cleanup_has_no_events_block' => ''
            ]);
        } else {
            $this->setVar([
                'cleanup_has_events_block' => ''
            ]);

            $this->parse('cleanup_has_no_events_block', 'cleanup_has_no_events');
        }

        $this->parse('cleanup_block', 'cleanup');
    }
}

