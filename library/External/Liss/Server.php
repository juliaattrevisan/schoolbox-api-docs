<?php
/**
 * External_Liss_Server
 *
 * In their SDK, configure it to connect to this place:
 * http://192.168.36.10/liss/liss.php
 *
 * You can accept their default schoolcode, but use alaress/alaress as
 * username/password tuple
 *
 * Execute calls in this order (belltimes and classes must happen before timetable,
 * otherwise nothing matches up properly - belltimes and classes table gets truncated
 * so you can run them as many times as you want before running timetable)
 *
 * Proposed Extensions/publishTeachers();
 * Proposed Extensions/publishStudents();
 * Publish/publishBellTimes();
 * Publish/publishClasses();
 * Publish/publishClassMemberships();
 * Publish/publishTimetable(); (this one might take a little while)
 *
 * Turn on the Schoolbox connector in External DB
 *
 * Handcraft a record in class_user with values (1, 424, 208)
 * Handcraft a homepage for 11CHE 1
 *
 * You can now view 208's timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use \Alaress\Lumberjack\Logger;
use External\Liss\Helper\PublishDailyData as PublishDailyDataHelper;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventRelations;
use Schoolbox\Calendar\EventType;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

class External_Liss_Server
{
    /**
     * Default logging message when attempting to use liss with a non-schoolbox connector
     */
    const WRONG_CONNECTOR_MESSAGE = 'Not using the schoolbox connector - exiting';

    /**
     * Log message when authentication fails
     */
    const AUTHENTICATION_FAILED = 'Authentication Failed';

    /**
     * Log message when owner can't be found
     */
    const MISSING_CALENDAR_OWNER = 'External Liss Calendar Owner not found';

    /**
     * Log message when no event types can be found
     */
    const NO_EVENT_TYPES = 'Cannot find a default event type';

    /**
     * Predefine a timetable_cycle record (this doesn't seem defined/accessible
     * via liss, for some reason - it might be in TimetableStructure ttStructure
     * which is not accessible to us)
     * @return object|\Schoolbox\External\TimetableCycle
     * @throws PDOException
     * @throws MapperException
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    static public function getCycle()
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $cycleMapper = new TimetableCycleMapper();

        try {
            /* TODO: Assume a particular cycle
             */
            $cycle = $cycleMapper->findById(1);

            $log->info("publishBellTimes: Defaulting to using first existing timetable cycle");
        } catch (MapperException $e) {
            $log->warning("publishBellTimes: Failed to find existing cycle, automatically creating one");

            $cycle = $cycleMapper->createModel();
            $cycle->setTitle('Main Timetable');

            /* TODO: Undo these hardcoded values at some point
             */
            $cycle->setCycleLength(10);
            $cycle->setDayLength(13);
            $cycle->setRegular(1);

            $cycleMapper->save($cycle);

            /* Force a timetable_campus record
             */
            $timetableCampusMapper = new TimetableCampusMapper();
            $timetableCampus = $timetableCampusMapper->createModel();
            $timetableCampus->setCycle($cycle);

            /** @var Campus $campus */
            $campus = CampusMapper::getInstance()->findById(1);

            $now = new Alaress_Date_Format();
            $timetableCampus->setCampus($campus);
            $timetableCampus->setStart($now->startOfYear());
            $timetableCampus->setReset(null);
            $timetableCampus->setFinish($now->endOfYear());
            $timetableCampus->setStartDay(1);

            $timetableCampusMapper->save($timetableCampus);
        }

        return $cycle;
    }


    /**
     * Start the Liss server with the list of available methods
     * and signatures here
     */
    public function start()
    {
        new xmlrpc_server(
            [
                'liss.getRooms' => [
                    'function' => 'External_Liss_Server::getRooms'
                ],
                'liss.getHello' => [
                    'function' => 'External_Liss_Server::getRooms'
                ],
                'liss.publishStudents' => [
                    'function' => 'External_Liss_Server::publishStudents',
                ],
                'liss.publishTeachers' => [
                    'function' => 'External_Liss_Server::publishTeachers',
                ],
                'liss.publishCalendar' => [
                    'function' => 'External_Liss_Server::publishCalendar',
                ],
                'liss.publishRooms' => [
                    'function' => 'External_Liss_Server::publishRooms',
                ],
                'liss.publishBellTimes' => [
                    'function' => 'External_Liss_Server::publishBellTimes',
                ],
                'liss.publishClasses' => [
                    'function' => 'External_Liss_Server::publishClasses',
                ],
                'liss.publishClassMemberships' => [
                    'function' => 'External_Liss_Server::publishClassMemberships',
                ],
                'liss.publishTimetable' => [
                    'function' => 'External_Liss_Server::publishTimetable',
                ],
                'liss.publishDailyData' => [
                    'function' => 'External_Liss_Server::publishDailyData'
                ],
            ]
        );
    }


    /**
     * Spawn a log message
     * @return Logger
     */
    static public function log()
    {
        $logFile = fopen(Cfg::get('logs_dir') . '/liss.log', 'a');
        return $log = new Logger($logFile, $logFile, Monolog\Logger::INFO, [], true);
    }


    /**
     * getRooms() is required to connect to the LissTester from
     * their SDK. Despite documentation saying that liss.hello
     * should be the first you implement, getRooms() is actually
     * called whenever you click Connect in the LissTester
     *
     * Don't delete it
     *
     * Discovered via apache mod_dumpio
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    static public function getRooms(xmlrpcmsg $message)
    {
        $log = self::log();

        $log->info("getRooms: unable to process request, not implemented");

        return self::generateResponse('got rooms');
    }


    /**
     * Publish students to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws PDOException
     */
    static public function publishClassMemberships(xmlrpcmsg $message)
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            $log->info("publishClassMemberships: processing request");
            $classInvolvementMapper = new ClassInvolvementMapper();
            $classEntryMapper = new ClassEntryMapper();
            $userMapper = new UserMapper();

            $classInvolvementMapper->truncate();

            $allData = $message->params[1]->me['array'];

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                try {
                    $user = $userMapper->findByExternalId(trim((string)$struct['StudentId']->me['string']));

                    /** @var ClassEntry $class */
                    $class = $classEntryMapper->findOneByColumn('code', trim((string)$struct['ClassCode']->me['string']));

                    $classInvolvement = $classInvolvementMapper->createModel();
                    $classInvolvement->setClass($class);
                    $classInvolvement->setUser($user);
                    $classInvolvementMapper->save($classInvolvement);
                } catch (MapperException $e) {
                    $log->warning(sprintf("publishClassMemberships: Failed to put student %d in %s: %s", trim((string)$struct['StudentId']->me['string']), trim((string)$struct['ClassCode']->me['string']), $e->getMessage()));
                }
            }

            $log->info("publishClassMemberships: completed request");

            return self::generateResponse('Class Memberships imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish Teachers to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    static public function publishTeachers(xmlrpcmsg $message)
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $log->info("publishTeachers: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $log->info("publishTeachers: " . json_encode($allData));

            return self::generateResponse('Teachers Imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish students to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    static public function publishStudents(xmlrpcmsg $message)
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $log->info("publishStudents: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $log->info("publishStudents: " . json_encode($allData));

            return self::generateResponse('Students Imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish Calendar to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    static public function publishCalendar(xmlrpcmsg $message)
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $log->info("publishCalendar: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $log->info("publishCalendar: " . json_encode($allData));

            return self::generateResponse('Calendar Imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish Rooms to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    static public function publishRooms(xmlrpcmsg $message)
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */

            $log->info("publishRooms: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $log->info("publishRooms: " . json_encode($allData));

            return self::generateResponse('Rooms Imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish classes to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws MapperException
     * @throws PDOException
     */
    static public function publishClasses(xmlrpcmsg $message)
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {

            $log->info("publishClasses: processing request");

            /* Truncate everything before re-adding
             */
            $classEntryMapper = new ClassEntryMapper();
            $classEntryMapper->truncate();

            $allData = $message->params[2]->me['array'];
            $classCount = 0;

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                $classEntry = $classEntryMapper->createModel();
                $classEntry->setName((string)$struct['CourseName']->me['string']);
                $classEntry->setCode(trim((string)$struct['ClassCode']->me['string']));
                $classEntry->setDescription('');

                $classEntryMapper->save($classEntry);
                $classCount++;
            }

            $log->info(sprintf("publishClasses: completed adding %d classes", $classCount));

            return self::generateResponse('Classes Imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish timetable to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws PDOException
     * @throws \Schoolbox\Mapper\Exception
     */
    static public function publishTimetable(xmlrpcmsg $message)
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {

            $log->info("publishTimetable: processing request");

            $timetablePeriodMapper = new TimetablePeriodMapper();
            $classEntryMapper = new ClassEntryMapper();
            $userMapper = new UserMapper();

            /* Truncate everything before re-adding
             */
            $classPeriodMapper = new ClassPeriodMapper();
            $classPeriodMapper->truncate();

            /* TODO: Default user for testing
             */
            $defaultUser = $userMapper->findById(1); // changed to alaress user for testing

            $allData = $message->params[1]->me['array'];

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                /* Find the Period data
                 */
                $allPeriods = $timetablePeriodMapper->findByColumnList([
                    'day' => trim((string)$struct['DayNumber']->me['i4']),
                    'title' => trim((string)$struct['Period']->me['string'])
                ]);

                /* Find the Class
                 */
                $allClasses = $classEntryMapper->findByColumn('code', trim($struct['ClassCode']->me['string']));

                /* Find the Teacher
                 */
                $allTeachers = $userMapper->findByColumn('synergy_id', trim($struct['TeacherCode']->me['string']));

                /* TODO: Ram in a duff account for testing
                 */
                if (!$allTeachers->count()) {
                    $allTeachers->add($defaultUser);
                }

                /* Check that all three are provided
                 * Save it!
                 */
                if ($allPeriods->count() && $allClasses->count() && $allTeachers->count()) {
                    $classPeriod = $classPeriodMapper->createModel();
                    $classPeriod->setClass($allClasses->first()->getId());
                    $classPeriod->setPeriod($allPeriods->first()->getId());
                    $classPeriod->setTeacher($allTeachers->first()->getId());
                    $classPeriod->setRoom((string)$struct['RoomCode']->me['string']);
                    $classPeriodMapper->save($classPeriod);
                } else {

                    /* These ones are broken */
                    if ($allPeriods->count() == 0) {
                        $log->warning(sprintf("publishTimetable: Failed to find period %s on day %s", trim((string)$struct['DayNumber']->me['i4']), trim((string)$struct['Period']->me['string'])));
                    }

                    if ($allClasses->count() == 0) {
                        $log->warning(sprintf("publishTimetable: Failed to find class %s", trim($struct['ClassCode']->me['string'])));
                    }

                    if ($allTeachers->count() == 0) {
                        $log->warning(sprintf("publishTimetable: Failed to find teacher %s", trim($struct['TeacherCode']->me['string'])));
                    }
                }
            }

            $log->info("publishTimetable: completed request");

            return self::generateResponse('Timetable Imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish timetable to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    static public function publishBellTimes(xmlrpcmsg $message)
    {
        $log = self::log();

        /* Requires schoolbox connector
         */
        if (!self::isSchoolboxConnector()) {
            $log->info(self::WRONG_CONNECTOR_MESSAGE);
            return self::generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            $log->info("publishBellTimes: processing request");
            $cycle = External_Liss_Server::getCycle();

            $allData = $message->params[2]->me['array'];

            /* Truncate everything before re-adding */
            $timetablePeriodMapper = new TimetablePeriodMapper();
            $timetablePeriodMapper->truncate();

            $collection = new Collection();

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                $dayNumber = (string)$struct['DayNumber']->me['i4'];

                /** @var TimetablePeriod $timetablePeriod */
                $timetablePeriod = $timetablePeriodMapper->createModel();
                $timetablePeriod->setCycle($cycle);
                $timetablePeriod->setTitle((string)$struct['Period']->me['string']);
                $timetablePeriod->setDay($dayNumber);
                $timetablePeriod->setStart(new \Alaress_Date_Format((string)$struct['StartTime']->me['string'], 'H:i'));
                $timetablePeriod->setFinish(new \Alaress_Date_Format((string)$struct['EndTime']->me['string'], 'H:i'));

                /* TODO: Undo these hardcoded values at some point
                  */
                $timetablePeriod->setUsable(1);

                /* Start a collection for a day, if it's not already a collection
                 */
                if (!($collection->getOffset($dayNumber) instanceof Collection)) {
                    $collection->setOffset($dayNumber, new Collection());
                }

                $collection->getOffset($dayNumber)->add($timetablePeriod);
            }

            $collection->reindex();

            $log->info(sprintf("publishBellTimes: Collated %d days and %d periods into timetable structure", $collection->count(), $collection->first()->count()));

            foreach ($collection as $dayCollection) {
                $i = 0;

                foreach ($dayCollection as $timetablePeriod) {
                    $timetablePeriod->setSequence($i);
                    $timetablePeriodMapper->save($timetablePeriod);

                    $i++;
                }
            }

            $log->info(sprintf("publishBellTimes: Saved timetable periods, process completed", $collection->count()));

            return self::generateResponse('BellTimes Imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * publish daily timetable and room changes to schoolbox
     *
     * Not in use yet, but causes errors if schools try to send
     * this data. Separated from getRooms() to allow for future use.
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     */
    static public function publishDailyData(xmlrpcmsg $message)
    {
        $log = self::log();

        $configLoader = new ConfigLoader();
        $helper = new PublishDailyDataHelper($configLoader);
        $eventMapper = EventMapper::getInstance();
        $eventTypeMapper = EventTypeMapper::getInstance();
        $folderMapper = FolderMapper::getInstance();
        $userMapper = UserMapper::getInstance();

        /* Put the json_encoded payload into a separate log message
         * just in case the log message itself is inconclusive -_-
         */
        $includePayloadInLogging = false;

        /* These event types are ignored
         */
        $exclusions = ['Duty', 'Study', 'RollClass'];

        /* Owner set in config
         */
        try {
            $author = $userMapper->findById($configLoader->get('external_liss_calendar_owner'));

        } catch (MapperException $e) {
            $log->critical(self::MISSING_CALENDAR_OWNER);
            return self::generateResponse(self::MISSING_CALENDAR_OWNER);
        }

        /* Have an owner - continue normally
         */
        $authenticator = new External_Liss_Authenticator($message->params[0], $log, $configLoader);

        if ($authenticator->isAuthenticated()) {
            /* Delete all previous versions of events using the same source
             */
            $helper->deleteEventsFromSource();

            /* Set a default event type for now - use the first one in the list
             */
            /** @var EventType $eventType */
            $allEventTypes = $eventTypeMapper->findAll([
                'limit' => 1,
                'id' => 'ASC'
            ]);

            if ($allEventTypes->count()) {
                $eventType = $allEventTypes->first();

            } else {
                $log->critical(self::NO_EVENT_TYPES);
                return self::generateResponse(self::NO_EVENT_TYPES);
            }

            /* Save it specifically for this user without mass reset which
             * is what EventRelations normally does - we only need access
             * to specific operations for saving personal/folder events
             */
            $eventRelations = new EventRelations(null, null, null);

            /* Set up all events in a nicer format
             */
            $allEvents = $helper->reformatAllEvents($message->params[3]->me['array']);

            /* Now do it in a way that makes sense
             * Map everything together and persist it to the db
             */
            $skipCount = [
                'noClassCode' => 0,
                'noType' => 0,
                'invalidType' => 0,
                'unknownUser' => 0,
                'unknownUserIds' => [],
                'total' => 0,
            ];

            /* Number of event records created
             */
            $instancesCreated = 0;
            $instancesHomepageCreated = 0;
            $instancesPersonalCreated = 0;

            $total = count($allEvents);

            foreach ($allEvents as $currentEvent) {
                /* Skip events that don't have a type
                 */
                if (!strlen($currentEvent['type'])) {
                    $log->warning("Skipping event \"{$currentEvent['className']}\" with guid \"{$currentEvent['guid']}\": no type");

                    if ($includePayloadInLogging) {
                        $log->warning("Payload for guid \"{$currentEvent['guid']}\": " . json_encode($currentEvent));
                        $log->warning('');
                    }

                    $skipCount['noType']++;
                    $skipCount['total']++;

                    continue;
                }

                /* Skip events that have a particular type
                 */
                if (in_array($currentEvent['type'], $exclusions)) {
                    $log->warning("Skipping event \"{$currentEvent['className']}\" with guid \"{$currentEvent['guid']}\": has excluded type \"{$currentEvent['type']}\"");

                    if ($includePayloadInLogging) {
                        $log->warning("Payload for guid \"{$currentEvent['guid']}\": " . json_encode($currentEvent));
                        $log->warning('');
                    }

                    $skipCount['invalidType']++;
                    $skipCount['total']++;

                    continue;
                }

                /* Skip events that don't have any classCode value
                 * This supposedly never happens, but I don't trust
                 */
                if (!strlen(trim($currentEvent['classCode']))) {
                    $log->warning("Skipping {$currentEvent['className']} ({$currentEvent['guid']}): no class codes provided");
                    $skipCount['noClassCode']++;
                    $skipCount['total']++;

                    continue;
                }

                /* Otherwise continue to process under the guise that it will be either a
                 * homepage (folder) event, or a personal event
                 */

                /** @var Event $event */
                $event = $eventMapper->createModel();
                $event->setOwner($author);
                $event->setAuthor($author);
                $event->setType($eventType);
                $event->setLocation($currentEvent['rooms']);
                $event->setTitle($currentEvent['className']);
                $event->setDetail($currentEvent['detail']);
                $event->setStartDateTime($currentEvent['startDateTime']);
                $event->setEndDateTime($currentEvent['endDateTime']);

                /* Decide between homepage events and personal events
                 * Determine which one based on the existence of a schoolbox folder that
                 * contains the classCode value
                 *
                 * We don't do Campus events in Edval (no way to identify)
                 *
                 * saveEventSource() throws mapper exceptions, so I don't want that affecting
                 * the outcome of this decision
                 */
                try {
                    $folder = $folderMapper->findByCode($currentEvent['classCode']);
                }
                catch (MapperException $e) {
                    $folder = null;
                }

                if ($folder instanceof Folder) {

                    /* Homepage event
                     */
                    $event->setAccess(Event::ACCESS_GROUP);
                    $eventMapper->save($event);

                    $eventRelations->saveFolderRelation($event, [$folder->getId()]);
                    $helper->saveEventSource($event, $currentEvent['guid']);
                    $log->info(sprintf("Processed homepage event \"%s\" with guid \"%s\", %s - %s", $event->getTitle(), $currentEvent['guid'], $event->getStartDateTime()->getDatabase(), $event->getEndDateTime()->getDatabase()));

                    $instancesHomepageCreated++;
                } else {

                    /* Personal event
                     */
                    $event->setAccess(Event::ACCESS_PERSONAL);

                    /* Teachers and Students can be treated synonymously
                     */
                    $combined = array_merge($currentEvent['students'], $currentEvent['teacherIds']);

                    foreach ($combined as $currentExternalId) {
                        try {
                            $user = $userMapper->findByExternalId($currentExternalId);
                            $event->setId(null);
                            $event->setOwner($user);
                            $eventMapper->save($event);

                            $eventRelations->savePersonalRelation($event);
                            $helper->saveEventSource($event, $currentEvent['guid']);

                            $log->info(sprintf("Processed personal event \"%s\" with guid \"%s\" for user \"%s\", %s - %s", $event->getTitle(), $currentEvent['guid'], $currentExternalId, $event->getStartDateTime()->getDatabase(), $event->getEndDateTime()->getDatabase()));

                            $instancesPersonalCreated++;
                        } catch (MapperException $e) {
                            $log->warning(sprintf("Skipping event \"%s\" with guid \"%s\" for user \"%s\": no Schoolbox account found for external id \"%s\"",
                                $event->getTitle(), $currentEvent['guid'], $currentExternalId, $currentExternalId
                            ));

                            if ($includePayloadInLogging) {
                                $log->warning("Payload for guid \"{$currentEvent['guid']}\": " . json_encode($currentEvent));
                                $log->warning('');
                            }

                            /* Number of occurrences of an unknown user
                             */
                            $skipCount['unknownUser']++;

                            /* Uniqueified list of unknown user ids
                             * This count is not necessarily the same as unknownUser count
                             */
                            $skipCount['unknownUserIds'][$currentExternalId] = $currentExternalId;
                        }
                    }
                }
            }

            /* Log diagnostics
             */
            $processed = $total-$skipCount['total'];

            $log->info("Total Processed: {$processed}/{$total}");
            $log->info("Total Skipped: {$skipCount['total']}/{$total}");
            $log->info("Skipped (no class code): {$skipCount['noClassCode']}/{$skipCount['total']}");
            $log->info("Skipped (no type): {$skipCount['noType']}/{$skipCount['total']}");
            $log->info("Skipped (invalid type): {$skipCount['invalidType']}/{$skipCount['total']}");
            $log->info("Unknown user assignations: {$skipCount['unknownUser']}");

            /* A list of all the unknown user ids
             */
            $unknownUserIds = array_filter($skipCount['unknownUserIds']);

            if (count($unknownUserIds)) {
                asort($unknownUserIds);

                $unknownUserIds = implode(', ', $unknownUserIds);

                $log->info("Unknown user ids: {$unknownUserIds}");
            }

            $log->info("----------------------------------------------");

            /* Number of instances created
             */
            $totalInstances = $instancesPersonalCreated + $instancesHomepageCreated;

            $log->info("Event Instances Spawned: {$totalInstances}. This should match event_source record count for this source.");
            $log->info("Personal Instances: {$instancesPersonalCreated}");
            $log->info("Homepage Instances: {$instancesHomepageCreated}");
            $log->info('');

            return self::generateResponse('Daily Data Imported');
        }

        return self::generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * This installation is currently using the schoolbox connector
     *
     * @return bool
     */
    static private function isSchoolboxConnector()
    {
        $config = new ConfigLoader();
        $connector = $config->get('external_type');

        return $connector == 'schoolbox';
    }

    /**
     * A xmlrpcresp factory
     *
     * @param $message
     * @return xmlrpcresp
     */
    static private function generateResponse($message)
    {
        return new xmlrpcresp(new xmlrpcval($message, 'string'));
    }
}
