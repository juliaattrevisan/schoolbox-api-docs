<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss;

use Alaress\Lumberjack\Logger;
use PhpXmlRpc\Server;

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
     * @param string $username
     * @param string $password
     * @param string $schoolCode
     * @param string $logDir
     */
    public function __construct(
        string $username,
        string $password,
        string $schoolCode,
        string $logDir
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->schoolCode = $schoolCode;
        $this->logDir = $logDir;

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

                'liss.publishTimetable' => [
                    'function' => [$this, 'publishTimetable'],
                ],


//                'liss.publishClasses' => [
//                    'function' => [$this, 'publishClasses'],
//                ],
//
//                'liss.publishClassMemberships' => [
//                    'function' => [$this, 'publishClassMemberships'],
//                ],
//
//                'liss.publishBellTimes' => [
//                    'function' => [$this, 'publishBellTimes'],
//                ],
//
//                'liss.publishDailyData' => [
//                    'function' => [$this, 'publishDailyData'],
//                ],

//                /* Not implemented; returns a placeholder Response
//                 */
//                'liss.getRooms' => [
//                    'function' => [$this,'getRooms'],
//                ],
//                'liss.publishStudents' => [
//                    'function' => [$this,'publishStudents'],
//                ],
//                'liss.publishTeachers' => [
//                    'function' => [$this,'publishTeachers'],
//                ],
//                'liss.publishCalendar' => [
//                    'function' => [$this, 'publishCalendar'],
//                ],
//                'liss.publishRooms' => [
//                    'function' => [$this, 'publishRooms'],
//                ],
            ]
        );
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