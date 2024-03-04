<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint;

use Alaress\Lumberjack\Logger;
use PhpXmlRpc\Request;
use PhpXmlRpc\Response;
use PhpXmlRpc\Server;
use PhpXmlRpc\Value;
use Psr\Log\LoggerInterface;
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
     * Constructor.
     *
     * @param int                                      $externalEnable    Whether the external db was enabled
     * @param string                                   $externalType      Whether the external db is the "schoolbox" connector or something else (it only works with "schoolbox" connector)
     * @param string                                   $username          LISS username, as per config
     * @param string                                   $password          LISS password, as per config
     * @param string                                   $defaultType       A string denoting an arbitrary event type that we attribute when the supplied type is non-meaningful
     * @param string                                   $teacherIdentifier Decide whether to use teacherIds or teacherCodes; configured in admin
     * @param \Schoolbox\External\Connector\Log\Mapper $importLogMapper   Log the import in the suds control panel
     * @param \Schoolbox\Resource\Storage\Mapper       $storageMapper     Log liss payloads to a local file storage record
     */
    public function __construct(
        private int $externalEnable,
        private string $externalType,
        private string $username,
        #[SensitiveParameter] private string $password,
        private string $defaultType,
        private string $teacherIdentifier,
        private string $sourceName,
        private \Schoolbox\Resource\Storage\FilesystemProxy $filesystemProxy,
        private \Schoolbox\User $superuser,
        private \Schoolbox\External\Connector\Log\Mapper $importLogMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper
    ) {

        $this->defaultType = trim($defaultType);
        $this->teacherIdentifier = trim($teacherIdentifier);

        $this->log = new Logger(STDOUT, STDOUT, \Monolog\Logger::INFO, [], true, 'liss');

        $this->storageMapper->getDatabase()->query('SET MAX_STATEMENT_TIME=0;');
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
        $this->log()->debug('Logging LISS payload to local storage');

        (new PayloadCache($this->storageMapper, $this->filesystemProxy, $this->log))->cache(
            $this->sourceName,
            PayloadCache::PAYLOAD_DAILY_DATA,
            $this->superuser,
            $message
        );

        return $this->generateResponse('publishDailyData stored.');
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
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        $this->log()->debug('Logging LISS payload to local storage');

        (new PayloadCache($this->storageMapper, $this->filesystemProxy, $this->log))->cache(
            $this->sourceName,
            PayloadCache::PAYLOAD_CLASS_MEMBERSHIPS,
            $this->superuser,
            $message
        );

        return $this->generateResponse('publishClassMemberships stored.');
    }


    /* Legacy liss implementation
     */

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
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        $this->log()->debug('Logging LISS payload to local storage');

        (new PayloadCache($this->storageMapper, $this->filesystemProxy, $this->log))->cache(
            $this->sourceName,
            PayloadCache::PAYLOAD_BELLTIMES,
            $this->superuser,
            $message
        );

        return $this->generateResponse('publishBellTimes stored');
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
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        $this->log()->debug('Logging LISS payload to local storage');

        (new PayloadCache($this->storageMapper, $this->filesystemProxy, $this->log))->cache(
            $this->sourceName,
            PayloadCache::PAYLOAD_CLASSES,
            $this->superuser,
            $message
        );

        return $this->generateResponse('publishClasses stored');
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
        if (!(new Authenticator($this->externalEnable, $this->externalType, $this->username, $this->password, $this->log(), $message->getParam(0)))->isAuthenticated()) {
            return $this->generateResponse(self::AUTHENTICATION_FAILED);
        }

        /* Cache locally whatever liss sent to us
         * This allows you to replay locally
         */
        $this->log()->debug('Logging LISS payload to local storage');

        (new PayloadCache($this->storageMapper, $this->filesystemProxy, $this->log))->cache(
            $this->sourceName,
            PayloadCache::PAYLOAD_TIMETABLE,
            $this->superuser,
            $message
        );

        return $this->generateResponse('publishTimetable stored.');
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
