<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Lumberjack\Logger;
use PhpXmlRpc\Request;
use PhpXmlRpc\Response;
use PhpXmlRpc\Server;
use PhpXmlRpc\Value;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Classes;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Cleanup;
use Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar\Prepare;

/**
 * Class Receiver
 *
 * This receives all the requests sent to us via the liss client.
 *
 * Someone at the school pushes the relevant buttons in the client which
 * then posts data to us, at a url of our choosing. We cannot initiate
 * this data transfer ourselves; we require a human at a school to do it.
 *
 * We have the development toolkit https://liss.net.au/docs/getting-started/
 * which happily tells us to use json without providing json data in the
 * toolkit (sigh) so it forces us to use xmlrpc
 *
 * The toolkit is the only thing we have to test/develop this on our own.
 */
class Receiver
{
    /**
     * An arbitrarily-chosen string to denote where these events came from
     */
    public const SOURCE_NAME = 'suds_liss';


    /**
     * A file in your vagrant: /var/log/schoolbox/vagrantx
     */
    private const LOG_FILE = 'liss.log';


    /**
     * Log message when authentication fails
     */
    private const AUTHENTICATION_FAILED = 'Authentication failed; check username/password/schoolCode in your instance.';


    /**
     * Whether the external db was enabled
     *
     * @var int|null
     */
    private ?int $externalEnable = null;


    /**
     * Whether the external db is the "schoolbox" connector
     * or something else (it only works with "schoolbox" connector)
     *
     * @var string|null
     */
    private ?string $externalType = null;


    /**
     * @var string|null
     */
    private ?string $username = null;

    /**
     * @var string|null
     */
    private ?string $password = null;


    /**
     * @var string|null
     */
    private ?string $schoolCode = null;


    /**
     * A csv list of event types that we should skip
     *
     * @var array|null
     */
    private ?array $exclusions = null;


    /**
     * A string denoting an arbitrary event type that we attribute
     * when the supplied type is non-meaningful
     *
     * @var string|null
     */
    private ?string $defaultType = null;


    /**
     * Decide whether to use teacherIds or teacherCodes
     * Configured in admin
     *
     * @var string|null
     */
    private ?string $teacherIdentifier = null;


    /**
     * @var string|null
     */
    private ?string $logDir = null;


    /**
     * Logs the things that happened, in a text file
     *
     * @var Logger|null
     */
    private ?Logger $log = null;


    /**
     * Constructor.
     *
     * @param int    $externalEnable
     * @param string $externalType
     * @param string $username
     * @param string $password
     * @param string $schoolCode
     * @param string $exclusions
     * @param string $defaultType
     * @param string $teacherIdentifier
     * @param string $logDir
     */
    public function __construct(
        int $externalEnable,
        string $externalType,
        string $username,
        string $password,
        string $schoolCode,
        string $exclusions,
        string $defaultType,
        string $teacherIdentifier,
        string $logDir
    ) {
        $this->externalEnable = $externalEnable;
        $this->externalType = $externalType;
        $this->username = $username;
        $this->password = $password;
        $this->schoolCode = $schoolCode;

        /* (configured in admin) A csv list of event types that should be ignored
         * and a default event type that should be attributed to a liss event
         * when it didn't specify a type (note that it will be possible that you
         * can specify a default event type this is also excluded)
         */
        $this->exclusions = array_map('trim', explode(',', $exclusions));
        $this->defaultType = trim($defaultType);
        $this->teacherIdentifier = trim($teacherIdentifier);

        /* Path to a log file which you can: tail -n100 -f
         * so you can see what's going on. Continuously appends.
         */
        $this->logDir = $logDir;

        $logFile = fopen(sprintf(
            '%s/%s',
            $logDir,
            self::LOG_FILE
        ), 'ab');

        $this->log = new Logger($logFile, $logFile, \Monolog\Logger::INFO, [], true);
    }


    /**
     * Start the Liss server with the list of available methods
     * and signatures here
     */
    public function start(): void
    {
        new Server(
            [
                /* A test that appears on the Authorisation tab in the
                 * liss client app; checks whether your authorisation
                 * settings were correct but gives no user feedback:
                 * response window opens and shuts immediately of its
                 * own accord.
                 */
                'liss.getHello' => [
                    'function' => [$this,'getRooms'],
                ],

                /* Time-based variant of timetable data is found here
                 */
                'liss.publishDailyData' => [
                    'function' => [$this, 'publishDailyData'],
                ],

                /* All these ones fulfil a cyclic timetable afaict
                 */
                'liss.publishTimetable' => [
                    'function' => [$this, 'publishTimetable'],
                ],

                'liss.publishClasses' => [
                    'function' => [$this, 'publishClasses'],
                ],

                'liss.publishClassMemberships' => [
                    'function' => [$this, 'publishClassMemberships'],
                ],

                'liss.publishBellTimes' => [
                    'function' => [$this, 'publishBellTimes'],
                ],

                /* Not implemented; returns a placeholder Response
                 */
                'liss.getRooms' => [
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
            ]
        );
    }


    /**
     * publish daily timetable and room changes to schoolbox
     *
     * @param Request $message
     * @return Response
     * @throws \InvalidArgumentException
     */
    public function publishDailyData(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator(
            $this->externalEnable,
            $this->externalType,
            $this->username,
            $this->password,
            $this->schoolCode,
            $this->log(),
            $message->getParam(0)
        ))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Since we are receiving a push from schools,
         * we are always in Perform mode
         */
        $perform = true;

        /* Pave the way
         */
        (new Prepare(
            $this->log(),
            $perform,
            \Schoolbox\External\Event\Mapper::getInstance(),
            \Schoolbox\External\ClassEntryMapper::getInstance(),
            \Schoolbox\External\ClassInvolvementMapper::getInstance(),
            \Schoolbox\External\ClassPeriodMapper::getInstance(),
            \Schoolbox\External\TimetablePeriodMapper::getInstance(),
            \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
            self::SOURCE_NAME
        ))->import();

        /* Do the import
         */
        (new Classes(
            $this->log(),
            $perform,
            $this->defaultType,
            $this->exclusions,
            $this->teacherIdentifier,
            \Schoolbox\External\Event\Mapper::getInstance(),
            \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
            \Schoolbox\External\ClassEntryMapper::getInstance(),
            \Schoolbox\External\ClassInvolvementMapper::getInstance(),
        ))->import($message);

        /* Cleanup after ourselves
         */
        (new Cleanup(
            $this->log(),
            $perform,
            new \Schoolbox\External\Event\Mapper(),
            self::SOURCE_NAME
        ))->import();

        return $this->generateResponse('publishDailyData finished.');
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
        return $this->generateResponse('getRooms finished.');
    }


    /**
     * Publish students to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishClassMemberships(Request $message): Response
    {
//        /* Requires schoolbox connector
//         */
//        if (!$this->isSchoolboxConnector()) {
//            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
//            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
//        }
//
//        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);
//
//        if ($authenticator->isAuthenticated()) {
//            $this->log()->info('publishClassMemberships: processing request');
//            $classInvolvementMapper = new ClassInvolvementMapper();
//            $classEntryMapper = new ClassEntryMapper();
//
//            $classInvolvementMapper->truncate();
//
//            $allData = $message->getParam(1)->me['array'];
//            $classAll = $classEntryMapper->findAll();
//            $classAll->indexBy('code');
//
//            $queryItems = array();
//            foreach ($allData as $currentData) {
//                $struct = $currentData->me['struct'];
//                $class = $classAll[trim((string)$struct['ClassCode']->me['string'])];
//                $external_id = trim((string)$struct['StudentId']->me['string']);
//                if ($class instanceof ClassEntry) {
//                    $queryItems[] = "(NULL, '" . $class->getId() . "', '" . $external_id . "')";
//                }
//            }
//
//            if (count($queryItems) > 0) {
//                $fullQuery = 'INSERT INTO sis_class_user (id, class_id, external_id) VALUES ' . implode(',', $queryItems) . ';';
//                try {
//                    // Manual bulk insert into the DB of "$fullquery"
//                    $this->externalDb->executeQuery($fullQuery);
//                } catch (Exception $e) {
//                    $this->log()->error(sprintf('publishClassMemberships: Failed to save class memberships: %s', $e->getMessage()));
//                }
//            }
//
//            $this->log()->info('publishClassMemberships: completed request');
//
//            return $this->generateResponse('Class Memberships imported');
//        }
//
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
//        /* Requires schoolbox connector
//         */
//        if (!$this->isSchoolboxConnector()) {
//            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
//            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
//        }
//
//        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);
//
//        if ($authenticator->isAuthenticated()) {
//            /* Respond without doing anything to avoid errors
//             */
//            $this->log()->info('publishTeachers: unable to process request, not implemented');
//
//            /* Log data for use */
//            $allData = $message->getParam(1)->me['array'];
//            $this->log()->info('publishTeachers: ' . json_encode($allData));
//
//            return $this->generateResponse('Teachers Imported');
//        }
//
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
//        /* Requires schoolbox connector
//         */
//        if (!$this->isSchoolboxConnector()) {
//            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
//            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
//        }
//
//        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);
//
//        if ($authenticator->isAuthenticated()) {
//            /* Respond without doing anything to avoid errors
//             */
//            $this->log()->info('publishStudents: unable to process request, not implemented');
//
//            /* Log data for use */
//            $allData = $message->getParam(1)->me['array'];
//            $this->log()->info('publishStudents: ' . json_encode($allData));
//
//            return $this->generateResponse('Students Imported');
//        }
//
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
//        /* Requires schoolbox connector
//         */
//        if (!$this->isSchoolboxConnector()) {
//            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
//            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
//        }
//
//        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);
//
//        if ($authenticator->isAuthenticated()) {
//            /* Respond without doing anything to avoid errors
//             */
//            $this->log()->info('publishCalendar: unable to process request, not implemented');
//
//            /* Log data for use */
//            $allData = $message->getParam(1)->me['array'];
//            $this->log()->info('publishCalendar: ' . json_encode($allData));
//
//            return $this->generateResponse('Calendar Imported');
//        }
//
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
//        /* Requires schoolbox connector
//         */
//        if (!$this->isSchoolboxConnector()) {
//            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
//            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
//        }
//
//        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);
//
//        if ($authenticator->isAuthenticated()) {
//            /* Respond without doing anything to avoid errors
//             */
//
//            $this->log()->info('publishRooms: unable to process request, not implemented');
//
//            /* Log data for use */
//            $allData = $message->getParam(1)->me['array'];
//            $this->log()->info('publishRooms: ' . json_encode($allData));
//
//            return $this->generateResponse('Rooms Imported');
//        }
//
        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish classes to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishClasses(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator(
            $this->externalEnable,
            $this->externalType,
            $this->username,
            $this->password,
            $this->schoolCode,
            $this->log(),
            $message->getParam(0)
        ))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        $this->log->info(json_encode($message->getParam(2)->me['array']));
        return $this->generateResponse(self::AUTHENTICATION_FAILED);

//        /* Requires schoolbox connector
//         */
//        if (!$this->isSchoolboxConnector()) {
//            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
//            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
//        }
//
//        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);
//
//        if ($authenticator->isAuthenticated()) {
//            $this->log()->info('publishClasses: processing request');
//
//            /* Truncate everything before re-adding
//             */
//            $classEntryMapper = new ClassEntryMapper();
//            $classEntryMapper->truncate();
//
//            $allData = $message->getParam(2)->me['array'];
//            $classCount = 0;
//
//            foreach ($allData as $currentData) {
//                $struct = $currentData->me['struct'];
//
//                $classEntry = $classEntryMapper->createModel();
//                $classEntry->setName((string)$struct['CourseName']->me['string']);
//                $classEntry->setCode(trim((string)$struct['ClassCode']->me['string']));
//                $classEntry->setDescription('');
//
//                $classEntryMapper->save($classEntry);
//                $classCount++;
//            }
//
//            $this->log()->info(sprintf('publishClasses: completed adding %d classes', $classCount));
//
//            return $this->generateResponse('Classes Imported');
//        }

        return $this->generateResponse(self::AUTHENTICATION_FAILED);
    }


    /**
     * Publish timetable to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishTimetable(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator(
            $this->externalEnable,
            $this->externalType,
            $this->username,
            $this->password,
            $this->schoolCode,
            $this->log(),
            $message->getParam(0)
        ))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        $this->log->info(json_encode($message->getParam(1)->me['array']));
        return $this->generateResponse(self::AUTHENTICATION_FAILED);

//            $this->log()->info('publishTimetable: processing request');
//
//            $timetablePeriodMapper = new TimetablePeriodMapper();
//            $classEntryMapper = new ClassEntryMapper();
//
//            /* Truncate everything before re-adding
//             */
//            $classPeriodMapper = new ClassPeriodMapper();
//            $classPeriodMapper->truncate();
//
//            /* Decide which externalId format to use via config var
//             */
//            $isTeacherIds = $this->isTeacherIds();
//
//            $allData = $message->getParam(1)->me['array'];
//
//            foreach ($allData as $currentData) {
//                /* An example $currentData that you can paste into a test.json
//                 * so you have a meaningful example of what you're working with
//                 * This came straight outta their 2020 devkit
//                 *
//                 * {"me":{"struct":{"DayNumber":{"me":{"i4":1},"mytype":1,"_php_class":null},"Period":{"me":{"string":"3"},"mytype":1,"_php_class":null},"ClassCode":{"me":{"string":"7PDH 2"},"mytype":1,"_php_class":null},"EdvalClassCode":{"me":{"string":"7PDH2"},"mytype":1,"_php_class":null},"TeacherId":{"me":{"string":"79455"},"mytype":1,"_php_class":null},"TeacherCode":{"me":{"string":"ANDN"},"mytype":1,"_php_class":null},"RoomCode":{"me":{"string":"B3"},"mytype":1,"_php_class":null}}},"mytype":3,"_php_class":null}
//                 */
//                $struct = $currentData->me['struct'];
//
//                /* Find the Period data
//                 */
//                $allPeriods = $timetablePeriodMapper->findByColumnList([
//                    'day' => trim((string)$struct['DayNumber']->me['i4']),
//                    'title' => trim((string)$struct['Period']->me['string'])
//                ]);
//
//                /* Find the Class
//                 */
//                $allClasses = $classEntryMapper->findByColumn('code', trim($struct['ClassCode']->me['string']));
//
//                /* Decide which externalId format to use via config var
//                 */
//                if ($isTeacherIds) {
//                    $externalId = trim((string)$struct['TeacherId']->me['string']);
//                } else {
//                    $externalId = trim((string)$struct['TeacherCode']->me['string']);
//                }
//
//                /* Check that all three are provided
//                 * Save it!
//                 */
//                if ($allPeriods->count() && $allClasses->count()) {
//                    $classPeriod = $classPeriodMapper->createModel();
//                    $classPeriod->setClass($allClasses->first()->getId());
//                    $classPeriod->setPeriod($allPeriods->first()->getId());
//                    $classPeriod->setTeacher($externalId);
//                    $classPeriod->setRoom(trim((string)$struct['RoomCode']->me['string']));
//                    $classPeriodMapper->save($classPeriod);
//                } else {
//                    /* These ones are broken */
//                    if ($allPeriods->count() === 0) {
//                        $this->log()->warning(sprintf("publishTimetable: Failed to find period %s on day %s", trim((string)$struct['DayNumber']->me['i4']), trim((string)$struct['Period']->me['string'])));
//                    }
//
//                    if ($allClasses->count() === 0) {
//                        $this->log()->warning(sprintf("publishTimetable: Failed to find class %s", trim($struct['ClassCode']->me['string'])));
//                    }
//                }
//            }
//
//            $this->log()->info("publishTimetable: completed request");
//
//            return $this->generateResponse('Timetable Imported');
//        }
//

    }


    /**
     * Publish timetable to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishBellTimes(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator(
            $this->externalEnable,
            $this->externalType,
            $this->username,
            $this->password,
            $this->schoolCode,
            $this->log(),
            $message->getParam(0)
        ))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        $this->log->info(json_encode($message->getParam(2)->me['array']));
        return $this->generateResponse(self::AUTHENTICATION_FAILED);

//        /* Requires schoolbox connector
//         */
//        if (!$this->isSchoolboxConnector()) {
//            $this->log()->info(self::WRONG_CONNECTOR_MESSAGE);
//            return $this->generateResponse(self::WRONG_CONNECTOR_MESSAGE);
//        }
//
//        $authenticator = new External_Liss_Authenticator($message->getParam(0), $this->log, $this->config);
//
//        if ($authenticator->isAuthenticated()) {
//            /* Index everything in a hash lookup, indexed hierarchically by cycle/day/period
//             * It turns out schools have multiple cycles of varying day lengths
//             */
//            $lookup = [];
//
//            $this->log()->info('publishBellTimes: processing request');
//
//            $allData = $message->getParam(2)->me['array'];
//
//            /* Truncate everything before re-adding */
//            $timetablePeriodMapper = new TimetablePeriodMapper();
//            $timetablePeriodMapper->truncate();
//
//            foreach ($allData as $currentData) {
//                $struct = $currentData->me['struct'];
//
//                /* Use $currentData['TtStructure']'s value as the cycle identifier
//                 * and load that if it exists, creating a new one if it doesn't
//                 */
//                $cycleIdentifier = (string)$struct['TtStructure']->me['string'];
//                $cycle           = $this->getCycle($cycleIdentifier);
//
//                $dayNumber = (string)$struct['DayNumber']->me['i4'];
//
//                $timetablePeriod = $timetablePeriodMapper->createModel()
//                    ->setCycle($cycle)
//                    ->setTitle((string)$struct['Period']->me['string'])
//                    ->setDay($dayNumber)
//                    ->setStart(new \Alaress_Date_Format((string)$struct['StartTime']->me['string'], 'H:i'))
//                    ->setFinish(new \Alaress_Date_Format((string)$struct['EndTime']->me['string'], 'H:i'));
//
//                /* TODO: Undo these hardcoded values at some point
//                  */
//                $timetablePeriod->setUsable(1);
//
//                /* Build a hash lookup of cycles, days and their periods
//                 */
//                if (!isset($lookup[$cycle->getId()][$dayNumber])) {
//                    $lookup[$cycle->getId()][$dayNumber] = [];
//                }
//
//                $lookup[$cycle->getId()][$dayNumber][] = $timetablePeriod;
//            }
//
//            /* Now traverse $lookup and assign sequence
//             * Take into account non-uniform day lengths by cross-referencing with $counts
//             */
//            foreach ($lookup as $cycleId => $dayData) {
//                $this->log()->info(sprintf(
//                    'publishBellTimes: Cycle %s has %s days',
//                    $cycleId,
//                    \count($dayData)
//                ));
//
//                foreach ($dayData as $dayIndex => $periods) {
//                    $this->log()->info(sprintf(
//                        'publishBellTimes: Cycle %s, day %s has %s periods',
//                        $cycleId,
//                        $dayIndex,
//                        \count($periods)
//                    ));
//
//                    /* Apply sequencing to every period in this day in this cycle
//                     */
//                    $i = 0;
//
//                    foreach ($periods as $timetablePeriod) {
//                        $timetablePeriod->setSequence($i);
//                        $timetablePeriodMapper->save($timetablePeriod);
//
//                        $i++;
//                    }
//                }
//            }
//
//            $this->log()->info(sprintf("publishBellTimes: Saved timetable periods, process completed"));
//
//            return $this->generateResponse('BellTimes Imported');
//        }
//
        return $this->generateResponse(self::AUTHENTICATION_FAILED);
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


    /**
     * Get the logger
     *
     * @return Logger
     */
    public function log(): Logger
    {
        return $this->log;
    }
}