<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Database\Connector\IConnector;
use Alaress\Lumberjack\Logger;
use PhpXmlRpc\Request;
use PhpXmlRpc\Response;
use PhpXmlRpc\Server;
use PhpXmlRpc\Value;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetableCycle;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use SensitiveParameter;

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
 *
 * There are two usages of this Receiver: data for the Schedule Timetable
 * and data for the Cyclic Timetable. Publish Daily Data and Publish Class
 * List Memberships are shared among both usages.
 */
class Receiver
{
    /**
     * Log message when authentication fails
     */
    private const AUTHENTICATION_FAILED = 'Authentication failed; check username/password/connector setting in your instance.';


    /**
     * LISS Protocol Version
     * @see https://liss.net.au/docs/common/authentication/
     */
    private const LISS_VERSION = 10000;


    /**
     * Name of agent (i.e. us)
     */
    private const AGENT_NAME = 'Schoolbox';


    /**
     * A list of event types that we should skip,
     * derived from a csv
     *
     * @var array
     */
    private array $exclusions;


    /**
     * Logs the things that happened, in a text file
     *
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /* Legacy LISS Implementation w/ the Cyclic Timetable
     *
     * These properties are used specifically to support by a legacy LISS implementation.
     * Data from a LISS push goes into the Cyclic Timetable using the following endpoints
     * in this order:
     *
     * Publish/publishDailyData(); (note this clears a lot of db tables via Prepare.php as per tradition)
     * Publish/publishBellTimes();
     * Publish/publishClasses();
     * Publish/publishClassMemberships();
     * Publish/publishTimetable(); (this one might take a little while)
     */

    /**
     * When TtStructure values are mysteriously empty, use this string
     * as the cycle's identifier and also its name. TtStructure is
     * sometimes provided in the input payload to distinguish cycles,
     * but in its absence we assume a default one instead.
     */
    private const DEFAULT_CYCLE_IDENTIFIER = 'Main Timetable';


    /**
     * This const corresponds to the enum for the config var external_liss_default_event_type
     * This is used to decide if we use numeric teacher ids, or alphanumeric teacher codes
     * If you're not using teacherId, you're presumed to be using teacherCode instead
     */
    private const TEACHER_IDENTIFIER_ID = 'teacherId';


    /**
     * A lookup of cycles
     *
     * @var TimetableCycle[]
     */
    private array $cycleCache = [];


    /**
     * A list of classEntries that we saw previously
     *
     * @var array
     */
    private array $classEntries = [];


    /**
     * Constructor.
     *
     * @param int                                      $externalEnable    Whether the external db was enabled
     * @param string                                   $externalType      Whether the external db is the "schoolbox" connector or something else (it only works with "schoolbox" connector)
     * @param string                                   $username          LISS username, as per config
     * @param string                                   $password          LISS password, as per config
     * @param string                                   $exclusionList     A csv list of event types that we should skip
     * @param string                                   $defaultType       A string denoting an arbitrary event type that we attribute when the supplied type is non-meaningful
     * @param string                                   $teacherIdentifier Decide whether to use teacherIds or teacherCodes; configured in admin
     * @param bool                                     $storageLogging    Decide whether to log actual liss payloads to local file storage
     * @param \Schoolbox\External\Connector\Log\Mapper $importLogMapper   Log the import in the suds control panel
     * @param \Schoolbox\Resource\Storage\Mapper       $storageMapper     Log liss payloads to a local file storage record
     */
    public function __construct(
        private int $externalEnable,
        private string $externalType,
        private string $username,
        #[SensitiveParameter] private string $password,
        private string $exclusionList,
        private string $defaultType,
        private string $teacherIdentifier,
        private bool $storageLogging,
        private string $sourceName,
        private \Schoolbox\User $superuser,
        private \Schoolbox\External\Connector\Log\Mapper $importLogMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper
    ) {
        /* (configured in admin) A csv list of event types that should be ignored
         * and a default event type that should be attributed to a liss event
         * when it didn't specify a type (note that it will be possible that you
         * can specify a default event type this is also excluded)
         */
        $this->exclusions = array_map('trim', explode(',', $this->exclusionList));
        $this->defaultType = trim($defaultType);
        $this->teacherIdentifier = trim($teacherIdentifier);

        $this->log = new Logger(STDOUT, STDOUT, \Monolog\Logger::INFO, [], true, 'liss');
    }


    /**
     * Start the Liss server with the list of available methods
     * and signatures here
     */
    public function start(): void
    {
        new Server([
            /* A test that appears on the Authorisation tab in the
             * liss client app; checks whether your authorisation
             * settings were correct but gives no user feedback:
             * response window opens and shuts immediately of its
             * own accord.
             */
            'liss.hello' => [
                'function' => [$this,'hello'],
            ],

            /* Time-based variant of timetable data is found here
             */
            'liss.publishDailyData' => [
                'function' => [$this, 'publishDailyData'],
            ],

            'liss.publishClassMemberships' => [
                'function' => [$this, 'publishClassMemberships'],
            ],

            /* All these ones fulfil a cyclic timetable to support
             * legacy liss implementations.
             *
             * The cyclic implementation shares publishDailyData
             * and publishClassListMemberships with the above
             */
            'liss.publishBellTimes' => [
                'function' => [$this, 'publishBellTimes'],
            ],
            'liss.publishClasses' => [
                'function' => [$this, 'publishClasses'],
            ],
            'liss.publishTimetable' => [
                'function' => [$this, 'publishTimetable'],
            ],

            /* Unused
             * Returns a placeholder Response
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
        ]);
    }


    /**
     * publish daily timetable and room changes to schoolbox
     *
     * @param Request $message
     * @return Response
     * @throws Exception
     * @throws \JsonException
     * @throws \PDOException
     * @throws \Throwable
     * @throws \UnexpectedValueException
     */
    public function publishDailyData(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated(false)) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        if ($this->storageLogging) {
            $this->log()->debug('Logging LISS payload to local storage');

            (new PayloadCache($this->storageMapper))->cache('dailydata', $this->superuser, $message);
        }

        /* This is the equivalent of a combined Scheduler.php and Factory.php
         * since the liss receiver immediately processes upon receiving data
         *
         * Since we are receiving a push from schools,
         * we are always in Perform mode
         */
        $composites = (new Factory())->produce(
            $this->log(),
            true,
            $this->defaultType,
            $this->exclusions,
            $this->teacherIdentifier,
            $this->sourceName,
            $message
        );

        /* Shank a database instance
         */
        $this->transact($this->importLogMapper->getDatabase(), $composites);

        return $this->generateResponse('publishDailyData finished.');
    }


    /**
     * Publish students to the Schoolbox
     *
     * @param Request $message
     * @return Response
     * @throws Exception
     * @throws \JsonException
     * @throws \PDOException
     * @throws \Throwable
     * @throws \UnexpectedValueException
     */
    public function publishClassMemberships(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        if ($this->storageLogging) {
            $this->log()->debug('Logging LISS payload to local storage');

            (new PayloadCache($this->storageMapper))->cache('classmemberships', $this->superuser, $message);
        }

        /* This is the equivalent of a combined Scheduler.php and Factory.php
         * since the liss receiver immediately processes upon receiving data
         *
         * Since we are receiving a push from schools,
         * we are always in Perform mode
         */
        $composites = (new GenericCollection(IStrategy::class))
            ->add(new TimetableCalendar\ClassMemberships(
                $this->log(),
                true,
                \Schoolbox\External\ClassEntryMapper::getInstance()->disableCache(),
                \Schoolbox\External\ClassInvolvementMapper::getInstance()->disableCache(),
                $message
            ));

        /* Shank a database instance
         */
        $this->transact($this->importLogMapper->getDatabase(), $composites);

        return $this->generateResponse('publishClassMemberships finished.');
    }


    /**
     * Execute each strat in sequence, just like Scheduler does
     *
     * @param IConnector        $db
     * @param GenericCollection $composites
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws \Throwable
     * @throws \UnexpectedValueException
     */
    private function transact(IConnector $db, GenericCollection $composites): void
    {
        /* Borrow some scheduler.php pieces
         */
        $runId = (new \Schoolbox\External\Connector\Log\Guid())->generate();
        $scriptName = \Schoolbox\External\Importer\Endpoint\Scheduler::SCHEDULER_SIS_SCRIPT_NAME;

        /* Always log the start of the run
         */
        $importLog = $this->importLogMapper->start($runId, $scriptName);

        try {
            $this->log()->debug(sprintf(
                'Beginning transaction'
            ));

            $db->beginTransaction();

            foreach ($composites as $composite) {
                $composite->import();
            }

            /* Augment the Factory's recipe with the finisher
             *
             * This way you don't have to remember doing it in every factory;
             * you literally cannot forget to do it. It'll only log the finish
             * if you didn't crash somewhere in the process
             */
            $this->importLogMapper->finish($importLog);

            /* Trim away ancient logs
             */
            $this->importLogMapper->prune();

            $this->log()->debug(sprintf(
                'Committing transaction'
            ));

            $db->commit();
        } catch (RuntimeException $e) {
            $this->log()->critical("Cannot access api endpoint: {$e->getMessage()}");

            $this->log()->debug(sprintf(
                'Rollback transaction'
            ));

            $db->rollBack();

            /* Something happened with the api; log it
             */
            $this->importLogMapper->error($importLog, $e->getMessage());
        } catch (\Throwable | \Exception $e) {
            $this->log()->critical("Cannot access api endpoint: {$e->getMessage()}");

            $this->log()->debug(sprintf(
                'Rollback transaction'
            ));

            $db->rollBack();

            /* Some kind of unexpected fatal; log it
             */
            $this->importLogMapper->error($importLog, $e->getMessage());

            /* Also rethrow it, so you get an error in the face
             */
            throw $e;
        }
    }


    /* Legacy liss implementation
     */

    /**
     * Publish timetable to the Schoolbox
     *
     * @param Request $message
     * @return Response
     * @throws Exception
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function publishBellTimes(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        if ($this->storageLogging) {
            $this->log()->debug('Logging LISS payload to local storage');

            (new PayloadCache($this->storageMapper))->cache('belltimes', $this->superuser, $message);
        }

        /* Index everything in a hash lookup, indexed hierarchically by cycle/day/period
         * It turns out schools have multiple cycles of varying day lengths
         */
        $lookup = [];

        $this->log()->info('publishBellTimes: processing request');

        $allData = $message->getParam(2)->me['array'];

        /* Truncate everything before re-adding
         * Note: This was supposed to already have been done via publishDailyData
         * which is supposed to go first
         *
         * $timetablePeriodMapper->truncate();
         */
        $timetablePeriodMapper = TimetablePeriodMapper::getInstance();

        foreach ($allData as $currentData) {
            $struct = $currentData->me['struct'];

            /* Use $currentData['TtStructure']'s value as the cycle identifier
             * and load that if it exists, creating a new one if it doesn't
             */
            $cycleIdentifier = trim((string)($struct['TtStructure']->me['string'] ?? null));
            $cycle           = $this->getCycle($cycleIdentifier);

            $dayNumber = trim((string)($struct['DayNumber']->me['i4'] ?? null));
            $periodName = trim((string)($struct['Period']->me['string'] ?? null));
            $startTime = trim((string)($struct['StartTime']->me['string'] ?? null));
            $finishTime = trim((string)($struct['EndTime']->me['string'] ?? null));

            $timetablePeriod = $timetablePeriodMapper->createModel()
                ->setCycle($cycle)
                ->setTitle($periodName)
                ->setDay($dayNumber)
                ->setStart(new \Alaress_Date_Format($startTime, 'H:i'))
                ->setFinish(new \Alaress_Date_Format($finishTime, 'H:i'));

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


    /**
     * Publish classes to the Schoolbox
     *
     * @param Request $message
     * @return Response
     * @throws Exception
     * @throws \JsonException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function publishClasses(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        if ($this->storageLogging) {
            $this->log()->debug('Logging LISS payload to local storage');

            (new PayloadCache($this->storageMapper))->cache('classes', $this->superuser, $message);
        }

        $this->log()->info('publishClasses: processing request');

        /* Truncate everything before re-adding
         * Note: This was supposed to already have been done via publishDailyData
         * which is supposed to go first
         *
         * $classEntryMapper->truncate();
         */
        $classEntryMapper = ClassEntryMapper::getInstance();

        $allData = $message->getParam(2)->me['array'];
        $classCount = 0;

        foreach ($allData as $currentData) {
            $struct = $currentData->me['struct'];
            $className = trim((string)($struct['CourseName']->me['string'] ?? null));
            $classCode = trim((string)($struct['ClassCode']->me['string'] ?? null));

            /* Constraints in the schema are case insensitive, unlike
             * these array keys - class codes sometimes appear like
             * "Speech Therapy" and "Speech therapy" so inserting them
             * twice will cause it to pack a sad. The classEntries array
             * treats these as unique whereas the db didn't
             */
            $existingKey = strtolower($classCode);

            if (isset($this->classEntries[$existingKey])) {
                /* Check if this class was added by this method
                 */
                $this->log->debug(sprintf(
                    '%s was already added in publishClasses; skipping',
                    $classCode
                ));
            } else {
                try {
                    /* Check if this class was added by a different method
                     */
                    $classEntry = $classEntryMapper->findOneByColumn('code', $classCode);

                    $this->log->debug(sprintf(
                        '%s was already added by some other means; skipping',
                        $classCode
                    ));
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $classEntry = $classEntryMapper->createModel()
                        ->setName($className)
                        ->setCode($classCode);

                    $classEntryMapper->save($classEntry);

                    $this->log->debug(sprintf(
                        'Adding %s',
                        $classCode
                    ));
                }

                /* Remember we saw it, in either outcome
                 */
                $this->classEntries[$existingKey] = $classEntry;
            }

            $classCount++;
        }

        $this->log()->info(sprintf('publishClasses: completed adding %d classes', $classCount));

        return $this->generateResponse('Classes Imported');
    }


    /**
     * Publish timetable to the Schoolbox
     *
     * @param Request $message
     * @return Response
     * @throws Exception
     * @throws \JsonException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function publishTimetable(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        if ($this->storageLogging) {
            $this->log()->debug('Logging LISS payload to local storage');

            (new PayloadCache($this->storageMapper))->cache('timetable', $this->superuser, $message);
        }

        $this->log()->info('publishTimetable: processing request');

        $timetablePeriodMapper = TimetablePeriodMapper::getInstance();
        $classEntryMapper = ClassEntryMapper::getInstance();

        /* Truncate everything before re-adding
         * Note: This was supposed to already have been done via publishDailyData
         * which is supposed to go first
         *
         * $classPeriodMapper->truncate();
         */
        $classPeriodMapper = ClassPeriodMapper::getInstance();

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

            /* Decide which externalId format to use via config var
             */
            if ($isTeacherIds) {
                $externalId = trim((string)($struct['TeacherId']->me['string'] ?? null));
            } else {
                $externalId = trim((string)($struct['TeacherCode']->me['string'] ?? null));
            }

            $dayNumber = trim((string)($struct['DayNumber']->me['i4'] ?? null));
            $periodName = trim((string)($struct['Period']->me['string'] ?? null));
            $classCode = trim((string)($struct['ClassCode']->me['string'] ?? null));
            $room = trim((string)($struct['RoomCode']->me['string'] ?? null));

            /* Find the Period data
             */
            $allPeriods = $timetablePeriodMapper->findByColumnList([
                'day' => $dayNumber,
                'title' => $periodName
            ]);

            /* Find the Class
             */
            $allClasses = $classEntryMapper->findByColumn('code', $classCode);

            /* Check that all three are provided
             * Save it!
             */
            if ($allPeriods->count() && $allClasses->count()) {
                $classPeriod = $classPeriodMapper->createModel()
                    ->setClass($allClasses->first()->getId())
                    ->setPeriod($allPeriods->first()->getId())
                    ->setTeacher($externalId)
                    ->setRoom($room);

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


    /**
     * Predefine a sis_timetable_cycle record (this doesn't seem defined/accessible
     * via liss, for some reason - it might be in TimetableStructure ttStructure
     * which is not accessible to us)
     *
     * @param string $identifier
     * @return TimetableCycle
     * @throws Exception
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    private function getCycle(string $identifier): TimetableCycle
    {
        /* If the $identifier is somehow an empty string, then it might not have
         * been set properly in the remote data source - just assume the usual
         * traditional default identifier in this case, and use that
         */
        $cycleIdentifier = $identifier ?: self::DEFAULT_CYCLE_IDENTIFIER;

        /* Return a cycle from the existing cached cycles
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
        } catch (\Schoolbox\Mapper\Exception $e) {
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
                ->setStart(\Alaress_Date_Format::now()->startOfYear())
                ->setFinish(\Alaress_Date_Format::now()->endOfYear())
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
     * Decide if we're going to use teacherIds as the externalId for staff
     * or whether we use teacherCodes instead
     *
     * @return bool
     */
    private function isTeacherIds(): bool
    {
        return self::TEACHER_IDENTIFIER_ID === $this->teacherIdentifier;
    }


    /* Unused endpoints but we needed to have them here just
     * in case people click it in their third party system
     */

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
        /* Requires schoolbox connector
         */
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('getRooms finished but unused.');
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
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishTeachers finished but unused.');
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
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishStudents finished but unused.');
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
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishCalendar finished but unused.');
    }


    /**
     * Publish Rooms to the Schoolbox
     *
     * @param Request $message
     * @return Response
     */
    public function publishRooms(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishRooms finished but unused.');
    }


    /* Generic/misc stuff
     */

    /**
     * https://liss.net.au/docs/api/hello/
     * @param Request $message
     * @return Response
     */
    public function hello(Request $message): Response
    {
        return new Response(new Value([
            'SIS'         => new Value(self::AGENT_NAME, Value::$xmlrpcString),
            'LissVersion' => new Value(self::LISS_VERSION, Value::$xmlrpcInt),
        ], Value::$xmlrpcStruct));
    }


    /**
     * A xmlrpcresp factory
     *
     * @param string $message
     * @return Response
     */
    private function generateResponse(string $message): Response
    {
        return new Response(new Value($message, 'string'));
    }


    /**
     * Get the logger
     * (This is also used to log random crashes in the liss.php)
     *
     * @return LoggerInterface
     */
    public function log(): LoggerInterface
    {
        return $this->log;
    }
}
