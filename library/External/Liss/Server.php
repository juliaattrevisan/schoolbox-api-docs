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
 * Handcraft a record in sis_class_user with values (1, 424, 208)
 * Handcraft a homepage for 11CHE 1
 *
 * You can now view 208's timetable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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

use Alaress\Database\Connector\IConnector;

class External_Liss_Server
{
    /** @var IConnector */
    private $externalDb;

    /** @var Logger */
    private $log;

    /**
     * External_Liss_Server constructor.
     *
     * @param IConnector|null $externalDb
     */
    public function __construct(IConnector $externalDb = null)
    {
        $this->externalDb = $externalDb;

        $logFile = fopen(Cfg::get('logs_dir') . '/liss.log', 'a');
        $this->log = new Logger($logFile, $logFile, Monolog\Logger::INFO, [], true);
    }
    /**
     * Default logging message when attempting to use liss with a non-schoolbox connector
     */
    private const WRONG_CONNECTOR_MESSAGE = 'External Database must be activate and the selected connector must be schoolbox - exiting';

    /**
     * Log message when authentication fails
     */
    private const AUTHENTICATION_FAILED = 'Authentication Failed';

    /**
     * Log message when owner can't be found
     */
    private const MISSING_CALENDAR_OWNER = 'External Liss Calendar Owner not found';

    /**
     * Log message when no event types can be found
     */
    private const NO_EVENT_TYPES = 'Cannot find a default event type';

    /**
     * Predefine a sis_timetable_cycle record (this doesn't seem defined/accessible
     * via liss, for some reason - it might be in TimetableStructure ttStructure
     * which is not accessible to us)
     * @return object|\Schoolbox\External\TimetableCycle
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function getCycle()
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $cycleMapper = new TimetableCycleMapper();

        try {
            /* TODO: Assume a particular cycle
             */
            $cycle = $cycleMapper->findById(1);

            $this->log()->info("publishBellTimes: Defaulting to using first existing timetable cycle");
        } catch (MapperException $e) {
            $this->log()->warning("publishBellTimes: Failed to find existing cycle, automatically creating one");

            $cycle = $cycleMapper->createModel();
            $cycle->setTitle('Main Timetable');

            /* TODO: Undo these hardcoded values at some point
             */
            $cycle->setCycleLength(10);
            $cycle->setDayLength(13);
            $cycle->setRegular(1);

            $cycleMapper->save($cycle);

            /* Force a sis_timetable_campus record
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
                    'function' => [$this,'getRooms'],
                ],
                'liss.getHello' => [
                    'function' => [$this,'getRooms'],
                ],
                'liss.publishStudents' => [
                    'function' => [$this,'publishStudents'],
                ],
                'liss.publishTeachers' => [
                    'function' => [$this,'publishTeachers'],
                ],
                'liss.publishCalendar' => [
                    'function' => [$this, 'publishCalendar'],
                ],
                'liss.publishRooms' => [
                    'function' => [$this, 'publishRooms'],
                ],
                'liss.publishBellTimes' => [
                    'function' => [$this, 'publishBellTimes'],
                ],
                'liss.publishClasses' => [
                    'function' => [$this, 'publishClasses'],
                ],
                'liss.publishClassMemberships' => [
                    'function' => [$this, 'publishClassMemberships'],
                ],
                'liss.publishTimetable' => [
                    'function' => [$this, 'publishTimetable'],
                ],
                'liss.publishDailyData' => [
                    'function' => [$this, 'publishDailyData'],
                ],
            ]
        );
    }


    /**
     * Get the logger
     * @return Logger
     */
    public function log()
    {
        return $this->log;
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
    public function getRooms(xmlrpcmsg $message): \xmlrpcresp
    {
        $this->log()->info("getRooms: unable to process request, not implemented");

        return $this->generateResponse('got rooms');
    }


    /**
     * Publish students to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws PDOException
     */
    public function publishClassMemberships(xmlrpcmsg $message): \xmlrpcresp
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            $this->log()->info("publishClassMemberships: processing request");
            $classInvolvementMapper = new ClassInvolvementMapper();
            $classEntryMapper = new ClassEntryMapper();
            $userMapper = new UserMapper();

            $classInvolvementMapper->truncate();

            $allData = $message->params[1]->me['array'];
            $classAll = $classEntryMapper->findAll();
            $classAll->indexBy('code');

            $queryItems = array();
            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                try {

                    /* TODO: Specify roletype here?
                     */
                    $user = $userMapper->findByExternalId(trim((string)$struct['StudentId']->me['string']));
                    $class = $classAll[trim((string)$struct['ClassCode']->me['string'])];
                    $queryItems[] = "(NULL, '".$class->getId()."', '".$user->getExternalId()."')";
                } catch (MapperException $e) {
                    $this->log()->warning(sprintf('publishClassMemberships: Failed to put student %s in %s: %s', trim((string)$struct['StudentId']->me['string']), trim((string)$struct['ClassCode']->me['string']), $e->getMessage()));
                }
            }

            if (count($queryItems) > 0) {
                $fullQuery = 'INSERT INTO sis_class_user (id, class_id, external_id) VALUES ' . implode($queryItems, ',') . ';';
                try {
                    // Manual bulk insert into the DB of "$fullquery"
                    $this->externalDb->executeQuery($fullQuery);
                } catch (Exception $e) {
                    $this->log()->error(sprintf('publishClassMemberships: Failed to save class memberships: %s', $e->getMessage()));
                }
            }

            $this->log()->info('publishClassMemberships: completed request');

            return $this->generateResponse('Class Memberships imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish Teachers to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    public function publishTeachers(xmlrpcmsg $message): \xmlrpcresp
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $this->log()->info("publishTeachers: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $this->log()->info("publishTeachers: " . json_encode($allData));

            return $this->generateResponse('Teachers Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish students to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    public function publishStudents(xmlrpcmsg $message): \xmlrpcresp
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $this->log()->info("publishStudents: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $this->log()->info("publishStudents: " . json_encode($allData));

            return $this->generateResponse('Students Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish Calendar to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    public function publishCalendar(xmlrpcmsg $message): \xmlrpcresp
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $this->log()->info("publishCalendar: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $this->log()->info("publishCalendar: " . json_encode($allData));

            return $this->generateResponse('Calendar Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * Publish Rooms to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    public function publishRooms(xmlrpcmsg $message)
    {


        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */

            $this->log()->info("publishRooms: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $this->log()->info("publishRooms: " . json_encode($allData));

            return $this->generateResponse('Rooms Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish classes to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function publishClasses(xmlrpcmsg $message): \xmlrpcresp
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {

            $this->log()->info("publishClasses: processing request");

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

            $this->log()->info(sprintf("publishClasses: completed adding %d classes", $classCount));

            return $this->generateResponse('Classes Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish timetable to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws UnexpectedValueException
     */
    public function publishTimetable(xmlrpcmsg $message): \xmlrpcresp
    {


        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {

            $this->log()->info("publishTimetable: processing request");

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
                    if ($allPeriods->count() === 0) {
                        $this->log()->warning(sprintf("publishTimetable: Failed to find period %s on day %s", trim((string)$struct['DayNumber']->me['i4']), trim((string)$struct['Period']->me['string'])));
                    }

                    if ($allClasses->count() === 0) {
                        $this->log()->warning(sprintf("publishTimetable: Failed to find class %s", trim($struct['ClassCode']->me['string'])));
                    }

                    if ($allTeachers->count() === 0) {
                        $this->log()->warning(sprintf("publishTimetable: Failed to find teacher %s", trim($struct['TeacherCode']->me['string'])));
                    }
                }
            }

            $this->log()->info("publishTimetable: completed request");

            return $this->generateResponse('Timetable Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish timetable to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function publishBellTimes(xmlrpcmsg $message): \xmlrpcresp
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, new ConfigLoader());

        if ($authenticator->isAuthenticated()) {
            $this->log()->info("publishBellTimes: processing request");
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

            $this->log()->info(sprintf("publishBellTimes: Collated %d days and %d periods into timetable structure", $collection->count(), $collection->first()->count()));

            foreach ($collection as $dayCollection) {
                $i = 0;

                foreach ($dayCollection as $timetablePeriod) {
                    $timetablePeriod->setSequence($i);
                    $timetablePeriodMapper->save($timetablePeriod);

                    $i++;
                }
            }

            $this->log()->info(sprintf("publishBellTimes: Saved timetable periods, process completed"));

            return $this->generateResponse('BellTimes Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
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
     * @throws UnexpectedValueException
     */
    public function publishDailyData(xmlrpcmsg $message): \xmlrpcresp
    {
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
            $this->log()->critical(self::MISSING_CALENDAR_OWNER);
            return $this->generateResponse(self::MISSING_CALENDAR_OWNER);
        }

        /* Have an owner - continue normally
         */
        $authenticator = new External_Liss_Authenticator($message->params[0], $this->log, $configLoader);

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
                $this->log()->critical(self::NO_EVENT_TYPES);
                return $this->generateResponse(self::NO_EVENT_TYPES);
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
                    $this->log()->warning("Skipping event \"{$currentEvent['className']}\" with guid \"{$currentEvent['guid']}\": no type");

                    if ($includePayloadInLogging) {
                        $this->log()->warning("Payload for guid \"{$currentEvent['guid']}\": " . json_encode($currentEvent));
                        $this->log()->warning('');
                    }

                    $skipCount['noType']++;
                    $skipCount['total']++;

                    continue;
                }

                /* Skip events that have a particular type
                 */
                if (\in_array($currentEvent['type'], $exclusions, false)) {
                    $this->log()->warning("Skipping event \"{$currentEvent['className']}\" with guid \"{$currentEvent['guid']}\": has excluded type \"{$currentEvent['type']}\"");

                    if ($includePayloadInLogging) {
                        $this->log()->warning("Payload for guid \"{$currentEvent['guid']}\": " . json_encode($currentEvent));
                        $this->log()->warning('');
                    }

                    $skipCount['invalidType']++;
                    $skipCount['total']++;

                    continue;
                }

                /* Skip events that don't have any classCode value
                 * This supposedly never happens, but I don't trust
                 */
                if (!strlen(trim($currentEvent['classCode']))) {
                    $this->log()->warning("Skipping {$currentEvent['className']} ({$currentEvent['guid']}): no class codes provided");
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
                $folder = $folderMapper->findForCampusAndClass('', $currentEvent['classCode']);

                if ($folder instanceof Folder) {

                    /* Homepage event
                     */
                    $event->setAccess(Event::ACCESS_GROUP);
                    $eventMapper->save($event);

                    $eventRelations->saveFolderRelation($event, [$folder->getId()]);
                    $helper->saveEventSource($event, $currentEvent['guid']);
                    $this->log()->info(sprintf("Processed homepage event \"%s\" with guid \"%s\", %s - %s", $event->getTitle(), $currentEvent['guid'], $event->getStartDateTime()->getDatabase(), $event->getEndDateTime()->getDatabase()));

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

                            $this->log()->info(sprintf("Processed personal event \"%s\" with guid \"%s\" for user \"%s\", %s - %s", $event->getTitle(), $currentEvent['guid'], $currentExternalId, $event->getStartDateTime()->getDatabase(), $event->getEndDateTime()->getDatabase()));

                            $instancesPersonalCreated++;
                        } catch (MapperException $e) {
                            $this->log()->warning(sprintf("Skipping event \"%s\" with guid \"%s\" for user \"%s\": no Schoolbox account found for external id \"%s\"",
                                $event->getTitle(), $currentEvent['guid'], $currentExternalId, $currentExternalId
                            ));

                            if ($includePayloadInLogging) {
                                $this->log()->warning("Payload for guid \"{$currentEvent['guid']}\": " . json_encode($currentEvent));
                                $this->log()->warning('');
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

            $this->log()->info("Total Processed: {$processed}/{$total}");
            $this->log()->info("Total Skipped: {$skipCount['total']}/{$total}");
            $this->log()->info("Skipped (no class code): {$skipCount['noClassCode']}/{$skipCount['total']}");
            $this->log()->info("Skipped (no type): {$skipCount['noType']}/{$skipCount['total']}");
            $this->log()->info("Skipped (invalid type): {$skipCount['invalidType']}/{$skipCount['total']}");
            $this->log()->info("Unknown user assignations: {$skipCount['unknownUser']}");

            /* A list of all the unknown user ids
             */
            $unknownUserIds = array_filter($skipCount['unknownUserIds']);

            if (count($unknownUserIds)) {
                asort($unknownUserIds);

                $unknownUserIds = implode(', ', $unknownUserIds);

                $this->log()->info("Unknown user ids: {$unknownUserIds}");
            }

            $this->log()->info("----------------------------------------------");

            /* Number of instances created
             */
            $totalInstances = $instancesPersonalCreated + $instancesHomepageCreated;

            $this->log()->info("Event Instances Spawned: {$totalInstances}. This should match event_source record count for this source.");
            $this->log()->info("Personal Instances: {$instancesPersonalCreated}");
            $this->log()->info("Homepage Instances: {$instancesHomepageCreated}");
            $this->log()->info('');

            return $this->generateResponse('Daily Data Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }

    /**
     * This installation is currently using the schoolbox connector
     *
     * @return bool
     */
    private function isSchoolboxConnector(): bool
    {
        $config = new ConfigLoader();

        /* External must be enabled
         */
        if (!$config->get('external_enable')) {
            return false;
        }

        /* External must also be schoolbox connector
         */
        return $config->get('external_type') === 'schoolbox';
    }

    /**
     * A xmlrpcresp factory
     *
     * @param $message
     * @return xmlrpcresp
     */
    private function generateResponse($message): \xmlrpcresp
    {
        return new xmlrpcresp(new xmlrpcval($message, 'string'));
    }
}
