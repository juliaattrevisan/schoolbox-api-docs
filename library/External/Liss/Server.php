<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Lumberjack\Logger;
use External\Liss\Helper\PublishDailyData as PublishDailyDataHelper;
use PhpXmlRpc\Request;
use PhpXmlRpc\Response;
use PhpXmlRpc\Server;
use PhpXmlRpc\Value;
use Schoolbox\Collection;
use Schoolbox\Calendar\Event;
use Schoolbox\Calendar\EventMapper;
use Schoolbox\Calendar\EventRelations;
use Schoolbox\Calendar\EventTypeMapper;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

/**
 * Class External_Liss_Server
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
 */
class External_Liss_Server
{

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
     * When TtStructure values are mysteriously empty, use this string
     * as the cycle's identifier and also its name
     */
    private const DEFAULT_CYCLE_IDENTIFIER = 'Main Timetable';


    /**
     * This const corresponds to the enum for the config var external_liss_default_event_type
     * This is used to decide if we use numeric teacher ids, or alphanumeric teacher codes
     * If you're not using teacherId, you're presumed to be using teacherCode instead
     */
    private const TEACHER_IDENTIFIER_ID = 'teacherId';


    /**
     * @var IConfigLoader
     */
    private $config;


    /**
     * @var IConnector
     */
    private $externalDb;


    /**
     * @var Logger
     */
    private $log;

    /**
     * @var TimetableCycle[]
     */
    private $cycleCache = [];

    /**
     * External_Liss_Server constructor.
     *
     * @param IConfigLoader   $config
     * @param IConnector|null $externalDb
     */
    public function __construct(IConfigLoader $config, IConnector $externalDb = null)
    {
        $this->config     = $config;
        $this->externalDb = $externalDb;

        $logFile = fopen($config->get('logs_dir') . '/liss.log', 'a');
        $this->log = new Logger($logFile, $logFile, Monolog\Logger::INFO, [], true);
    }


    /**
     * Predefine a sis_timetable_cycle record (this doesn't seem defined/accessible
     * via liss, for some reason - it might be in TimetableStructure ttStructure
     * which is not accessible to us)
     *
     * @param string $identifier
     * @return TimetableCycle
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    private function getCycle(string $identifier)
    {

        /* If the $identifier is somehow an empty string, then it might not have
         * been set properly in the remote data source - just assume the usual
         * traditional default identifier in this case, and use that
         */
        $cycleIdentifier = $identifier ?: self::DEFAULT_CYCLE_IDENTIFIER;

        /*
         * Return a cycle from the existing cached cycles
         */
        if (isset($this->cycleCache[$cycleIdentifier])) {
            return $this->cycleCache[$cycleIdentifier];
        }

        /* Try to find a Timetable Cycle matching $identifier
         * If one exists, load it and reuse it
         * Otherwise create a new one
         */
        $cycleMapper = TimetableCycleMapper::getInstance();

        try {
            $cycle = $cycleMapper->findOneByColumn('cycle_identifier', $cycleIdentifier);
        } catch (MapperException $e) {
            $this->log()->warning(sprintf(
                "publishBellTimes: Did not find a cycle for TtStructure identifier %s; creating a new one for it instead",
                $cycleIdentifier
            ));

            /* Assume some basic ideas about this new cycle because we haven't
             * seen anything warranting changing them yet
             *      a) It starts on Day 1
             *      b) It has a 10-day cycle length
             *      c) Each day has 13 slots in it
             *      d) Goes for the full calendar year
             */
            $cycle = $cycleMapper->createModel()
                ->setTitle($cycleIdentifier)
                ->setCycleIdentifier($cycleIdentifier)
                ->setStart(Alaress_Date_Format::now()->startOfYear())
                ->setFinish(Alaress_Date_Format::now()->endOfYear())

                /* TODO: Undo these hardcoded values at some point
                 */
                ->setStartDay(1)
                ->setCycleLength(10)
                ->setDayLength(13);

            $cycleMapper->save($cycle);
        }

        $this->cycleCache[$cycleIdentifier] = $cycle;

        return $cycle;
    }


    /**
     * Start the Liss server with the list of available methods
     * and signatures here
     */
    public function start()
    {
        new Server(
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
     * @param Request $message
     * @return Response
     */
    public function getRooms(Request $message): Response
    {
        $this->log()->info('getRooms: unable to process request, not implemented');

        return $this->generateResponse('got rooms');
    }


    /**
     * Publish students to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishClassMemberships(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);

        if ($authenticator->isAuthenticated()) {
            $this->log()->info('publishClassMemberships: processing request');
            $classInvolvementMapper = new ClassInvolvementMapper();
            $classEntryMapper = new ClassEntryMapper();

            $classInvolvementMapper->truncate();

            $allData = $message->getParam(1)->me['array'];
            $classAll = $classEntryMapper->findAll();
            $classAll->indexBy('code');

            $queryItems = array();
            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];
                $class = $classAll[trim((string)$struct['ClassCode']->me['string'])];
                $external_id = trim((string)$struct['StudentId']->me['string']);
                if ($class instanceof ClassEntry) {
                    $queryItems[] = "(NULL, '" . $class->getId() . "', '" . $external_id . "')";
                }
            }

            if (count($queryItems) > 0) {
                $fullQuery = 'INSERT INTO sis_class_user (id, class_id, external_id) VALUES ' . implode(',', $queryItems) . ';';
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
     * @param Request $message
     * @return Response
     */
    public function publishTeachers(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $this->log()->info('publishTeachers: unable to process request, not implemented');

            /* Log data for use */
            $allData = $message->getParam(1)->me['array'];
            $this->log()->info('publishTeachers: ' . json_encode($allData));

            return $this->generateResponse('Teachers Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish students to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishStudents(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $this->log()->info('publishStudents: unable to process request, not implemented');

            /* Log data for use */
            $allData = $message->getParam(1)->me['array'];
            $this->log()->info('publishStudents: ' . json_encode($allData));

            return $this->generateResponse('Students Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish Calendar to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishCalendar(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $this->log()->info('publishCalendar: unable to process request, not implemented');

            /* Log data for use */
            $allData = $message->getParam(1)->me['array'];
            $this->log()->info('publishCalendar: ' . json_encode($allData));

            return $this->generateResponse('Calendar Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish Rooms to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishRooms(Request $message)
    {

        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */

            $this->log()->info('publishRooms: unable to process request, not implemented');

            /* Log data for use */
            $allData = $message->getParam(1)->me['array'];
            $this->log()->info('publishRooms: ' . json_encode($allData));

            return $this->generateResponse('Rooms Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish classes to the Schoolbox
     *
     * @param Request $message
     * @return Response
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function publishClasses(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);

        if ($authenticator->isAuthenticated()) {
            $this->log()->info('publishClasses: processing request');

            /* Truncate everything before re-adding
             */
            $classEntryMapper = new ClassEntryMapper();
            $classEntryMapper->truncate();

            $allData = $message->getParam(2)->me['array'];
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

            $this->log()->info(sprintf('publishClasses: completed adding %d classes', $classCount));

            return $this->generateResponse('Classes Imported');
        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish timetable to the Schoolbox
     *
     * @param Request $message
     * @return Response
     * @throws PDOException
     * @throws MapperException
     * @throws UnexpectedValueException
     */
    public function publishTimetable(Request $message): Response
    {

        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);

        if ($authenticator->isAuthenticated()) {
            $this->log()->info('publishTimetable: processing request');

            $timetablePeriodMapper = new TimetablePeriodMapper();
            $classEntryMapper = new ClassEntryMapper();

            /* Truncate everything before re-adding
             */
            $classPeriodMapper = new ClassPeriodMapper();
            $classPeriodMapper->truncate();

            /* Decide which externalId format to use via config var
             */
            $isTeacherIds = $this->isTeacherIds();

            $allData = $message->getParam(1)->me['array'];

            foreach ($allData as $currentData) {
                /* An example $currentData that you can paste into a test.json
                 * so you have a meaningful example of what you're working with
                 * This came straight outta their 2020 devkit
                 *
                 * {"me":{"struct":{"DayNumber":{"me":{"i4":1},"mytype":1,"_php_class":null},"Period":{"me":{"string":"3"},"mytype":1,"_php_class":null},"ClassCode":{"me":{"string":"7PDH 2"},"mytype":1,"_php_class":null},"EdvalClassCode":{"me":{"string":"7PDH2"},"mytype":1,"_php_class":null},"TeacherId":{"me":{"string":"79455"},"mytype":1,"_php_class":null},"TeacherCode":{"me":{"string":"ANDN"},"mytype":1,"_php_class":null},"RoomCode":{"me":{"string":"B3"},"mytype":1,"_php_class":null}}},"mytype":3,"_php_class":null}
                 */
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

                /* Decide which externalId format to use via config var
                 */
                if ($isTeacherIds) {
                    $externalId = trim((string)$struct['TeacherId']->me['string']);
                } else {
                    $externalId = trim((string)$struct['TeacherCode']->me['string']);
                }

                /* Check that all three are provided
                 * Save it!
                 */
                if ($allPeriods->count() && $allClasses->count()) {
                    $classPeriod = $classPeriodMapper->createModel();
                    $classPeriod->setClass($allClasses->first()->getId());
                    $classPeriod->setPeriod($allPeriods->first()->getId());
                    $classPeriod->setTeacher($externalId);
                    $classPeriod->setRoom(trim((string)$struct['RoomCode']->me['string']));
                    $classPeriodMapper->save($classPeriod);
                } else {
                    /* These ones are broken */
                    if ($allPeriods->count() === 0) {
                        $this->log()->warning(sprintf("publishTimetable: Failed to find period %s on day %s", trim((string)$struct['DayNumber']->me['i4']), trim((string)$struct['Period']->me['string'])));
                    }

                    if ($allClasses->count() === 0) {
                        $this->log()->warning(sprintf("publishTimetable: Failed to find class %s", trim($struct['ClassCode']->me['string'])));
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
     * @param Request $message
     * @return Response
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function publishBellTimes(Request $message): Response
    {

        /* Requires schoolbox connector
         */
        if (!$this->isSchoolboxConnector()) {
            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
        }

        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);

        if ($authenticator->isAuthenticated()) {
            /* Index everything in a hash lookup, indexed hierarchically by cycle/day/period
             * It turns out schools have multiple cycles of varying day lengths
             */
            $lookup = [];

            $this->log()->info('publishBellTimes: processing request');

            $allData = $message->getParam(2)->me['array'];

            /* Truncate everything before re-adding */
            $timetablePeriodMapper = new TimetablePeriodMapper();
            $timetablePeriodMapper->truncate();

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                /* Use $currentData['TtStructure']'s value as the cycle identifier
                 * and load that if it exists, creating a new one if it doesn't
                 */
                $cycleIdentifier = (string)$struct['TtStructure']->me['string'];
                $cycle           = $this->getCycle($cycleIdentifier);

                $dayNumber = (string)$struct['DayNumber']->me['i4'];

                $timetablePeriod = $timetablePeriodMapper->createModel()
                    ->setCycle($cycle)
                    ->setTitle((string)$struct['Period']->me['string'])
                    ->setDay($dayNumber)
                    ->setStart(new \Alaress_Date_Format((string)$struct['StartTime']->me['string'], 'H:i'))
                    ->setFinish(new \Alaress_Date_Format((string)$struct['EndTime']->me['string'], 'H:i'));

                /* TODO: Undo these hardcoded values at some point
                  */
                $timetablePeriod->setUsable(1);

                /* Build a hash lookup of cycles, days and their periods
                 */
                if (!isset($lookup[$cycle->getId()][$dayNumber])) {
                    $lookup[$cycle->getId()][$dayNumber] = [];
                }

                $lookup[$cycle->getId()][$dayNumber][] = $timetablePeriod;
            }

            /* Now traverse $lookup and assign sequence
             * Take into account non-uniform day lengths by cross-referencing with $counts
             */
            foreach ($lookup as $cycleId => $dayData) {
                $this->log()->info(sprintf(
                    'publishBellTimes: Cycle %s has %s days',
                    $cycleId,
                    \count($dayData)
                ));

                foreach ($dayData as $dayIndex => $periods) {
                    $this->log()->info(sprintf(
                        'publishBellTimes: Cycle %s, day %s has %s periods',
                        $cycleId,
                        $dayIndex,
                        \count($periods)
                    ));

                    /* Apply sequencing to every period in this day in this cycle
                     */
                    $i = 0;

                    foreach ($periods as $timetablePeriod) {
                        $timetablePeriod->setSequence($i);
                        $timetablePeriodMapper->save($timetablePeriod);

                        $i++;
                    }
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
     * @param Request $message
     * @return Response
     * @throws InvalidArgumentException
     * @throws InvalidColumnException
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function publishDailyData(Request $message): Response
    {
        $configLoader = $this->config;
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
        $exclusions = array_map('trim', explode(',', $this->config->get('external_liss_exclusions')));

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
        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $configLoader);

        if ($authenticator->isAuthenticated()) {
            /* Delete all previous versions of events using the same source
             */
            $helper->deleteEventsFromSource();

            /* Get an indexed set of known luEventType records
             * If this is empty, just leave
             */
            $allEventTypes = $eventTypeMapper->findAll()->indexBy('type');

            if (!$allEventTypes->count()) {
                $this->log()->critical(self::NO_EVENT_TYPES);
                return $this->generateResponse(self::NO_EVENT_TYPES);
            }

            /* Save it specifically for this user without mass reset which
             * is what EventRelations normally does - we only need access
             * to specific operations for saving personal/folder events
             */
            $eventRelations = new EventRelations(null, null, null, null, false);

            /* Set up all events in a nicer format
             */
            $allEvents = $helper->reformatAllEvents($message->getParam(3)->me['array']);

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

            /* Default event type, specified in config
             */
            $defaultEventType = trim((string)$this->config->get('external_liss_default_event_type'));

            /* Decide which externalId format to use via config var
             */
            $isTeacherIds = $this->isTeacherIds();

            foreach ($allEvents as $lissEvent) {
                /* $lissEvent is a condensed version of what they send to us,
                 * structured by the helper class
                 */
                $currentEvent = $lissEvent;

                /* Event doesn't have a type; see if we specified one in config
                 * If we did, give it that event type instead, then carry onwards
                 */
                if (trim($currentEvent['type']) === '') {
                    if ('' === $defaultEventType) {
                        /* Skip events that don't have a type, because we didn't define
                         * a default event type in config
                         */
                        $this->log()->warning("Skipping event \"{$currentEvent['className']}\" with guid \"{$currentEvent['guid']}\": no type");

                        if ($includePayloadInLogging) {
                            $this->log()->warning("Payload for guid \"{$currentEvent['guid']}\": " . json_encode($currentEvent));
                            $this->log()->warning('');
                        }

                        $skipCount['noType']++;
                        $skipCount['total']++;

                        /* Skip processing this event entirely
                         */
                        continue;
                    }

                    /* Assign a default one and carry on as if this event
                     * always had this type to start with
                     */
                    $currentEvent['type'] = $defaultEventType;

                    $this->log()->debug(sprintf(
                        'Assigning default event type "%s" to event %s with guid %s',
                        $defaultEventType,
                        $currentEvent['className'],
                        $currentEvent['guid']
                    ));

                    if ($includePayloadInLogging) {
                        $this->log()->warning("Payload for guid \"{$currentEvent['guid']}\": " . json_encode($currentEvent));
                        $this->log()->warning('');
                    }
                }

                /* Skip events that have a particular type
                 * This also applies if you exclude the default one you chose in config
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
                if (trim($currentEvent['classCode']) === '') {
                    $this->log()->warning("Skipping {$currentEvent['className']} ({$currentEvent['guid']}): no class codes provided");
                    $skipCount['noClassCode']++;
                    $skipCount['total']++;

                    continue;
                }

                /* The type that is currently specified also has a corresponding
                 * luEventType record; use that luEventType instance
                 *
                 * Otherwise default to the first instance in the list (this will occur when the
                 * default you specified in config doesn't exist as an luEventType instance)
                 */
                $eventType = $allEventTypes[$currentEvent['type']] ?? $allEventTypes->first();

                /* Otherwise continue to process under the guise that it will be either a
                 * homepage (folder) event, or a personal event
                 */
                $event = $eventMapper->createModel();
                $event->setOwner($author);
                $event->setAuthor($author);
                $event->setType($eventType);
                $event->setLocation($currentEvent['rooms']);
                $event->setTitle($currentEvent['className']);
                $event->setDetail($currentEvent['detail']);
                $event->setStartDateTime($currentEvent['startDateTime']);
                $event->setEndDateTime($currentEvent['endDateTime']);
                $event->setCampusCollection(new Collection());

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
                    $this->log()->info(sprintf('Processed homepage event "%s" with guid "%s", %s - %s', $event->getTitle(), $currentEvent['guid'], $event->getStartDateTime()->getDatabase(), $event->getEndDateTime()->getDatabase()));

                    $instancesHomepageCreated++;
                } else {
                    /* Personal event
                     */
                    $event->setAccess(Event::ACCESS_PERSONAL);

                    /* Decide which externalId format to use via config var
                     */
                    if ($isTeacherIds) {
                        $teacherExternalIds = $currentEvent['teacherIds'];
                    } else {
                        $teacherExternalIds = $currentEvent['teacherCodes'];
                    }

//                    $this->log->warning(json_encode($currentEvent));
                    $this->log->warning(json_encode($teacherExternalIds));

                    /* Teachers and Students can be treated synonymously
                     */
                    $combined = array_merge($currentEvent['students'], $teacherExternalIds);

                    foreach ($combined as $currentExternalId) {
                        try {
                            $user = $userMapper->findByExternalId($currentExternalId);
                            $event->setId(null);
                            $event->setOwner($user);
                            $eventMapper->save($event);

                            $eventRelations->savePersonalRelation($event);
                            $helper->saveEventSource($event, $currentEvent['guid']);

                            $this->log()->info(sprintf('Processed personal event "%s" with guid "%s" for user "%s", %s - %s', $event->getTitle(), $currentEvent['guid'], $currentExternalId, $event->getStartDateTime()->getDatabase(), $event->getEndDateTime()->getDatabase()));

                            $instancesPersonalCreated++;
                        } catch (MapperException $e) {
                            $this->log()->warning(sprintf(
                                'Skipping event "%s" with guid "%s" for user "%s": no Schoolbox account found for external id "%s"',
                                $event->getTitle(),
                                $currentEvent['guid'],
                                $currentExternalId,
                                $currentExternalId
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
            $processed = $total - $skipCount['total'];

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

            $this->log()->info('----------------------------------------------');

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
        /* External must be enabled
         */
        if (!$this->config->get('external_enable')) {
            return false;
        }

        /* External must also be schoolbox connector
         */
        return $this->config->get('external_type') === 'schoolbox';
    }


    /**
     * Decide if we're going to use teacherIds as the externalId for staff
     * or whether we use teacherCodes instead
     *
     * @return bool
     */
    private function isTeacherIds(): bool
    {
        return self::TEACHER_IDENTIFIER_ID === trim((string)$this->config->get('external_liss_teacher_identifier'));
    }


    /**
     * A xmlrpcresp factory
     *
     * @param string $message
     * @return Response
     */
    private function generateResponse($message): Response
    {
        return new Response(new Value($message, 'string'));
    }
}
