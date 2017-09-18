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
 * @version     SVN: $Id$
 */

use \Alaress\Lumberjack\Logger;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\UserMapper;
use Schoolbox\External\ClassEntry;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\TimetableCampusMapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriod;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\Exception as MapperException;

class External_Liss_Server
{


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
            array(
                'liss.getRooms'                => array(
                    'function' => 'External_Liss_Server::getRooms'
                ),
                'liss.getHello'                => array(
                    'function' => 'External_Liss_Server::getRooms'
                ),
                'liss.publishStudents'         => array(
                    'function' => 'External_Liss_Server::publishStudents',
                ),
                'liss.publishTeachers'         => array(
                    'function' => 'External_Liss_Server::publishTeachers',
                ),
                'liss.publishCalendar'         => array(
                    'function' => 'External_Liss_Server::publishCalendar',
                ),
                'liss.publishRooms'         => array(
                    'function' => 'External_Liss_Server::publishRooms',
                ),
                'liss.publishBellTimes'        => array(
                    'function' => 'External_Liss_Server::publishBellTimes',
                ),
                'liss.publishClasses'          => array(
                    'function' => 'External_Liss_Server::publishClasses',
                ),
                'liss.publishClassMemberships' => array(
                    'function' => 'External_Liss_Server::publishClassMemberships',
                ),
                'liss.publishTimetable'        => array(
                    'function' => 'External_Liss_Server::publishTimetable',
                ),
                'liss.publishDailyData'        => array(
                    'function' => 'External_Liss_Server::publishDailyData'
                ),
            )
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
        return new xmlrpcresp(new xmlrpcval('got rooms', 'string'));
    }

    /**
     * Publish students to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    static public function publishClassMemberships(xmlrpcmsg $message)
    {
        $log = self::log();

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

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
                } catch (\Exception $e) {
                    $log->warning(sprintf("publishClassMemberships: Failed to put student %d in %s: %s", trim((string)$struct['StudentId']->me['string']), trim((string)$struct['ClassCode']->me['string']), $e->getMessage()));
                }
            }

            $log->info("publishClassMemberships: completed request");

            return new xmlrpcresp(new xmlrpcval('Class Memberships imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
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

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $log->info("publishTeachers: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $log->info("publishTeachers: ".json_encode($allData));

            return new xmlrpcresp(new xmlrpcval('Teachers Imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
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

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $log->info("publishStudents: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $log->info("publishStudents: ".json_encode($allData));


            return new xmlrpcresp(new xmlrpcval('Students Imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
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

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */
            $log->info("publishCalendar: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $log->info("publishCalendar: ".json_encode($allData));

            return new xmlrpcresp(new xmlrpcval('Calendar Imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
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

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

        if ($authenticator->isAuthenticated()) {
            /* Respond without doing anything to avoid errors
             */

            $log->info("publishRooms: unable to process request, not implemented");

            /* Log data for use */
            $allData = $message->params[1]->me['array'];
            $log->info("publishRooms: ".json_encode($allData));

            return new xmlrpcresp(new xmlrpcval('Rooms Imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
    }


    /**
     * Publish classes to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     * @throws PDOException
     */
    static public function publishClasses(xmlrpcmsg $message)
    {
        $log = self::log();

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

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

            return new xmlrpcresp(new xmlrpcval('Classes Imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
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

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

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
                    'day'   => trim((string)$struct['DayNumber']->me['i4']),
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
                }
                else {

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

            return new xmlrpcresp(new xmlrpcval('Timetable Imported', 'string'));
        }

        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
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
        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

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
                $timetablePeriod->setCycle($cycle->getId());
                $timetablePeriod->setTitle((string)$struct['Period']->me['string']);
                $timetablePeriod->setDay($dayNumber);
                $timetablePeriod->setStart((string)$struct['StartTime']->me['string']);
                $timetablePeriod->setFinish((string)$struct['EndTime']->me['string']);

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

            return new xmlrpcresp(new xmlrpcval('BellTimes Imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
    }

    /**
     * publish daily timetable and room changes to schoolbox
     *
     * Not in use yet, but causes errors if schools try to send
     * this data. Separated from getRooms() to allow for future use.
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    static public function publishDailyData(xmlrpcmsg $message)
    {
        $log = self::log();

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

        if ($authenticator->isAuthenticated()) {
            $allData = $message->params[3]->me['array'];

            /* Log data for use */
            $log->info("publishDailyData: ".json_encode($allData));

            return new xmlrpcresp(new xmlrpcval('Daily Data Imported', 'string'));
        }

        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
    }
}
