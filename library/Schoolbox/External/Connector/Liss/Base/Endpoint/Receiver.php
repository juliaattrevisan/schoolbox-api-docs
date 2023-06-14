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
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;

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
     * A file in your vagrant: /var/log/schoolbox/vagrantx
     */
    private const LOG_FILE = 'liss.log';


    /**
     * Log message when authentication fails
     */
    private const AUTHENTICATION_FAILED = 'Authentication failed; check username/password/schoolCode in your instance.';


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


    /**
     * Constructor.
     *
     * @param int                                      $externalEnable    Whether the external db was enabled
     * @param string                                   $username          LISS username, as per config
     * @param string                                   $password          LISS password, as per config
     * @param string                                   $exclusionList     A csv list of event types that we should skip
     * @param string                                   $defaultType       A string denoting an arbitrary event type that we attribute when the supplied type is non-meaningful
     * @param string                                   $teacherIdentifier Decide whether to use teacherIds or teacherCodes; configured in admin
     * @param bool                                     $storageLogging    Decide whether to log actual liss payloads to local file storage
     * @param string                                   $logDir            Path to a local log file
     * @param \Schoolbox\External\Connector\Log\Mapper $importLogMapper   Log the import in the suds control panel
     * @param \Schoolbox\Resource\Storage\Mapper       $storageMapper     Log liss payloads to a local file storage record
     */
    public function __construct(
        private int $externalEnable,
        private string $username,
        private string $password,
        private string $exclusionList,
        private string $defaultType,
        private string $teacherIdentifier,
        private bool $storageLogging,
        private string $logDir,
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

        /* Path to a log file which you can: tail -n100 -f
         * so you can see what's going on. Continuously appends.
         */
        $logFile = fopen(sprintf(
            '%s/%s',
            $this->logDir,
            self::LOG_FILE
        ), 'ab');

        /* TODO Reset this log level to INFO once we are safely on the way
         */
        $this->log = new Logger($logFile, $logFile, \Monolog\Logger::DEBUG, [], true);
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

            /* All these ones fulfil a cyclic timetable afaict
             * We don't need them
             *
             * Returns a placeholder Response
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
     * @throws \PDOException
     * @throws \Throwable
     * @throws \UnexpectedValueException
     */
    public function publishDailyData(Request $message): Response
    {
        /* Requires schoolbox connector
         */
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        if ($this->storageLogging) {
            $this->log()->debug('Logging LISS payload to local storage');

            (new PayloadCache($this->storageMapper))->cache($this->superuser, $message);
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
            $message
        );

        /* Shank a database instance
         */
        $this->transact($this->importLogMapper->getDatabase(), $composites);

        return $this->generateResponse('publishDailyData finished.');
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('getRooms finished but unused.');
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishClassMemberships finished but unused.');
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishRooms finished but unused.');
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishClasses finished but unused.');
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishTimetable finished but unused.');
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
        if (!(new Authenticator($this->externalEnable, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        return $this->generateResponse('publishBellTimes finished but unused.');
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
