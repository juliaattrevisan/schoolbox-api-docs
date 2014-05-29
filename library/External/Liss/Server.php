<?php
/**
 * External_Liss_Server
 *
 * Execute calls in this order (belltimes and classes must happen before timetable)
 * publishBellTimes();
 * publishClasses();
 * publishTimetable();
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

require_once(dirname(__FILE__) . "/../../../vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpc.inc");
require_once(dirname(__FILE__) . "/../../../vendor/phpxmlrpc/phpxmlrpc/lib/xmlrpcs.inc");

class External_Liss_Server {

	/**
	 * A place for you to dump request data
	 *
	 * @var null
	 */
	static $logger = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 * Setup
	 */
	private function setup() {

	}

	/**
	 * Predefine a timetable_cycle record (this doesn't seem defined/accessible
	 * via liss, for some reason - it might be in TimetableStructure ttStructure
	 * which is not accessible to us)
	 *
	 * @return object|\Schoolbox\External\TimetableCycle
	 */
	static public function getCycle() {
		$cycleMapper = new \Schoolbox\External\TimetableCycleMapper();

		try {
			$cycle = $cycleMapper->findById(1);
		}
		catch (\Exception $e) {
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

			/* TODO: Undo these hardcoded values at some point
			 */
			$timetableCampus->setCampus(1);
			$timetableCampus->setStart('2014-01-01');
			$timetableCampus->setReset(null);
			$timetableCampus->setFinish('2014-12-31');
			$timetableCampus->setOffset(0);

			$timetableCampusMapper->save($timetableCampus);
		}

		return $cycle;
	}

	/**
	 * Start the Liss server with the list of available methods
	 * and signatures here
	 */
	public function start() {
		new xmlrpc_server(array(
			'liss.getRooms' => array(
				'function' => 'External_Liss_Server::getRooms'
			),
			'liss.publishClasses' => array(
				'function' => 'External_Liss_Server::publishClasses',
			),
			'liss.publishTimetable' => array(
				'function' => 'External_Liss_Server::publishTimetable',
			),
			'liss.publishBellTimes' => array(
				'function' => 'External_Liss_Server::publishBellTimes',
			),
		));
	}

	/**
	 * Spawn a log message
	 */
	static public function log($data) {
		$logger = new \Alaress_Log_File(dirname(__FILE__) . '/../../../mort.log', 'liss');
		$logger->log($data);
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
	public function getRooms(xmlrpcmsg $message) {
		return new xmlrpcresp(new xmlrpcval('got rooms', 'string'));
	}

	/**
	 * Publish classes to the Schoolbox
	 *
	 * @param xmlrpcmsg $message
	 * @return xmlrpcresp
	 */
	static public function publishClasses(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			$academicYear = $message->params[1];

			/* Truncate everything before re-adding
			 */
			$classEntryMapper = new \Schoolbox\External\ClassEntryMapper();
			$classEntryMapper->truncate();

			$allData = $message->params[2]->me['array'];

			foreach($allData as $currentData) {
				$actualInfo = $currentData->me['struct'];

				$classEntry = new \Schoolbox\External\ClassEntry();
				$classEntry->setName((string)$actualInfo['CourseName']->me['string']);
				$classEntry->setCode((string)$actualInfo['ClassCode']->me['string']);
				$classEntry->setDescription('');

				$classEntryMapper->save($classEntry);
			}

			return new xmlrpcresp(new xmlrpcval('Classes imported', 'string'));
		}

		return new xmlrpcresp(new xmlrpcval('Not authenticated', 'string'));
	}

	/**
	 * Publish timetable to the Schoolbox
	 *
	 * @param xmlrpcmsg $message
	 * @return xmlrpcresp
	 */
	static public function publishTimetable(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			$timetablePeriodMapper = new \Schoolbox\External\TimetablePeriodMapper();
			$classEntryMapper = new \Schoolbox\External\ClassEntryMapper();
			$userMapper = new \Schoolbox\UserMapper();

			/* Truncate everything before re-adding
			 */
			$classPeriodMapper = new \Schoolbox\External\ClassPeriodMapper();
			$classPeriodMapper->truncate();

			/* TODO: Default user for testing
			 */
			$defaultUser = $userMapper->findById(19);

			$allData = $message->params[1]->me['array'];

			foreach($allData as $currentData) {
				$struct = $currentData->me['struct'];

				/* Find the Period data
				 */
				$allPeriods = $timetablePeriodMapper->findByColumnList(array(
					'day' => $struct['DayNumber']->me['i4'],
					'title' => $struct['Period']->me['string']
				));

				/* Find the Class
				 */
				$allClasses = $classEntryMapper->findByColumn('code', $struct['ClassCode']->me['string']);

				/* Find the Teacher
				 */
				$allTeachers = $userMapper->findByColumn('synergy_id', $struct['TeacherId']->me['string']);

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
				}
				/* These ones are broken
				 */
				else {
					$counts = "{$allPeriods->count()} {$allClasses->count()} {$allTeachers->count()} for {$struct['ClassCode']->me['string']}\n";

					External_Liss_Server::log($counts);
				}
			}

			return new xmlrpcresp(new xmlrpcval('Something imported', 'string'));
		}

		return new xmlrpcresp(new xmlrpcval('Not authenticated', 'string'));
	}

	/**
	 * Publish timetable to the Schoolbox
	 *
	 * @param xmlrpcmsg $message
	 * @return xmlrpcresp
	 */
	static public function publishBellTimes(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);
		$ttStructure = $message->params[1];
		$cycle = External_Liss_Server::getCycle();

		if ($authenticator->isAuthenticated()) {
			$allData = $message->params[2]->me['array'];

			/* Truncate everything before re-adding
			 */
			$timetablePeriodMapper = new \Schoolbox\External\TimetablePeriodMapper();
			$timetablePeriodMapper->truncate();

			$collection = new \Schoolbox\Collection();

			foreach($allData as $currentData) {
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

				/* Start a collection for a day
				 */
				if ($collection->getOffset($dayNumber) instanceof \Schoolbox\Collection) {
				}
				else {
					$collection->setOffset($dayNumber, new \Schoolbox\Collection());
				}

				$collection->getOffset($dayNumber)->add($timetablePeriod);
			}

			$collection->reindex();

			foreach($collection as $dayCollection) {
				$i = 0;

				foreach($dayCollection as $timetablePeriod) {
					$timetablePeriod->setSequence($i);
					$timetablePeriodMapper->save($timetablePeriod);

					$i++;
				}
			}

			return new xmlrpcresp(new xmlrpcval('Something imported', 'string'));
		}

		return new xmlrpcresp(new xmlrpcval('Not authenticated', 'string'));
	}
}