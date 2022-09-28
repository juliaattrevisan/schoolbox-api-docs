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
use Psr\Log\LoggerInterface;
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
     * @var int
     */
    private int $externalEnable;


    /**
     * Whether the external db is the "schoolbox" connector
     * or something else (it only works with "schoolbox" connector)
     *
     * @var string
     */
    private string $externalType;


    /**
     * @var string
     */
    private string $username;

    /**
     * @var string
     */
    private string $password;


    /**
     * @var string
     */
    private string $schoolCode;


    /**
     * A csv list of event types that we should skip
     *
     * @var array
     */
    private array $exclusions;


    /**
     * A string denoting an arbitrary event type that we attribute
     * when the supplied type is non-meaningful
     *
     * @var string
     */
    private string $defaultType;


    /**
     * Decide whether to use teacherIds or teacherCodes
     * Configured in admin
     *
     * @var string
     */
    private string $teacherIdentifier;


    /**
     * Logs the things that happened, in a text file
     *
     * @var Logger
     */
    private Logger $log;


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
                 * We don't need them
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

        return $this->generateResponse('publishCalendar finished but unused.');
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

        return $this->generateResponse('publishBellTimes finished but unused.');
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
     * @return LoggerInterface
     */
    public function log(): LoggerInterface
    {
        return $this->log;
    }
}