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

class External_Liss_Server
{


    /**
     * Predefine a timetable_cycle record (this doesn't seem defined/accessible
     * via liss, for some reason - it might be in TimetableStructure ttStructure
     * which is not accessible to us)
     *
     * @return object|\Schoolbox\External\TimetableCycle
     */
    static public function getCycle()
    {
        $log = self::log();
        $cycleMapper = new \Schoolbox\External\TimetableCycleMapper();

        try {
            /* TODO: Assume a particular cycle
             */
            $cycle = $cycleMapper->findById(1);
            $log->info("publishBellTimes: Defaulting to using first existing timetable cycle");
        } catch (\Exception $e) {
            $log->warning("publishBellTimes: Failed to find existing cycle, automatically creating one");
            $cycle = new \Schoolbox\External\TimetableCycle();
            $cycle->setTitle('Main Timetable');

            /* TODO: Undo these hardcoded values at some point
             */
            $cycle->setCycleLength(10);
            $cycle->setDayLength(13);
            $cycle->setRegular(1);

            $cycleMapper->save($cycle);

            /* Force a timetable_campus record
             */
            $timetableCampusMapper = new \Schoolbox\External\TimetableCampusMapper();
            $timetableCampus = new \Schoolbox\External\TimetableCampus();
            $timetableCampus->setCycle($cycle->getId());

            $now = new Alaress_Date_Format();
            $timetableCampus->setCampus(\Schoolbox\CampusMapper::getInstance()->findById(1));
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
            )
        );
    }

    /**
     * Spawn a log message
     * @return Logger
     */
    static public function log()
    {
        $logFile = fopen(Cfg::get('logs_dir') . '/liss.log');
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
            $classInvolvementMapper = new \Schoolbox\External\ClassInvolvementMapper();
            $classEntryMapper = new \Schoolbox\External\ClassEntryMapper();
            $userMapper = new \Schoolbox\UserMapper();

            $classInvolvementMapper->truncate();

            $allData = $message->params[1]->me['array'];

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                try {
                    $user = $userMapper->findByExternalId(trim((string)$struct['StudentId']->me['string']));
                    $class = $classEntryMapper->findOneByColumn('code', trim((string)$struct['ClassCode']->me['string']));

                    $classInvolvement = new \Schoolbox\External\ClassInvolvement();
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

            return new xmlrpcresp(new xmlrpcval('Students Imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
    }

    /**
     * Publish classes to the Schoolbox
     *
     * @param xmlrpcmsg $message
     * @return xmlrpcresp
     */
    static public function publishClasses(xmlrpcmsg $message)
    {
        $log = self::log();

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

        if ($authenticator->isAuthenticated()) {

            $log->info("publishClasses: processing request");

            $academicYear = $message->params[1];

            /* Truncate everything before re-adding
             */
            $classEntryMapper = new \Schoolbox\External\ClassEntryMapper();
            $classEntryMapper->truncate();

            $allData = $message->params[2]->me['array'];
            $classCount = 0;

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                $classEntry = new \Schoolbox\External\ClassEntry();
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
     */
    static public function publishTimetable(xmlrpcmsg $message)
    {
        $log = self::log();

        $authenticator = new External_Liss_Authenticator($message->params[0], $log);

        if ($authenticator->isAuthenticated()) {

            $log->info("publishTimetable: processing request");

            $timetablePeriodMapper = new \Schoolbox\External\TimetablePeriodMapper();
            $classEntryMapper = new \Schoolbox\External\ClassEntryMapper();
            $userMapper = new \Schoolbox\UserMapper();

            /* Truncate everything before re-adding
             */
            $classPeriodMapper = new \Schoolbox\External\ClassPeriodMapper();
            $classPeriodMapper->truncate();

            /* TODO: Default user for testing
             */
            $defaultUser = $userMapper->findById(1); // changed to alaress user for testing

            $allData = $message->params[1]->me['array'];

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                /* Find the Period data
                 */
                $allPeriods = $timetablePeriodMapper->findByColumnList(
                    array(
                        'day'   => trim((string)$struct['DayNumber']->me['i4']),
                        'title' => trim((string)$struct['Period']->me['string'])
                    )
                );

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
                    $classPeriod = new \Schoolbox\External\ClassPeriod();
                    $classPeriod->setClass($allClasses->first()->getId());
                    $classPeriod->setPeriod($allPeriods->first()->getId());
                    $classPeriod->setTeacher($allTeachers->first()->getId());
                    $classPeriod->setRoom((string)$struct['RoomCode']->me['string']);
                    $classPeriodMapper->save($classPeriod);
                } /* These ones are broken */
                else {
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
     */
    static public function publishBellTimes(xmlrpcmsg $message)
    {
        $log = self::log();
        $authenticator = new External_Liss_Authenticator($message->params[0], $log);
        $ttStructure = $message->params[1];

        if ($authenticator->isAuthenticated()) {
            $log->info("publishBellTimes: processing request");
            $cycle = External_Liss_Server::getCycle();

            $allData = $message->params[2]->me['array'];

            /* Truncate everything before re-adding */
            $timetablePeriodMapper = new \Schoolbox\External\TimetablePeriodMapper();
            $timetablePeriodMapper->truncate();

            $collection = new \Schoolbox\Collection();

            foreach ($allData as $currentData) {
                $struct = $currentData->me['struct'];

                $dayNumber = (string)$struct['DayNumber']->me['i4'];

                $timetablePeriod = new \Schoolbox\External\TimetablePeriod();
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
                if (!($collection->getOffset($dayNumber) instanceof \Schoolbox\Collection)) {
                    $collection->setOffset($dayNumber, new \Schoolbox\Collection());
                }

                $collection->getOffset($dayNumber)->add($timetablePeriod);
            }

            $collection->reindex();

            $log->info(sprintf("publishBellTimes: Collated %d days and %d periods into timetable structure",$collection->count(), $collection->first()->count()));


            foreach ($collection as $dayCollection) {
                $i = 0;

                foreach ($dayCollection as $timetablePeriod) {
                    $timetablePeriod->setSequence($i);
                    $timetablePeriodMapper->save($timetablePeriod);

                    $i++;
                }
            }
            $log->info(sprintf("publishBellTimes: Saved timetable periods, process completed",$collection->count()));

            return new xmlrpcresp(new xmlrpcval('BellTimes Imported', 'string'));
        }
        return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
    }
}
