<?php
/**
 * External_Liss_Server
 *
 * publishBellTimes();
 * publishClasses();
 * publishTimetable();
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
			'liss.publishDailyData' => array(
				'function' => 'External_Liss_Server::publishDailyData',
			),
			'liss.publishCalendar' => array(
				'function' => 'External_Liss_Server::publishCalendar',
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

			$allData = $message->params[2]->me['array'];

			$mapper = new \Schoolbox\External\ClassEntryMapper();

			foreach($allData as $currentData) {
				$actualInfo = $currentData->me['struct'];

				$classEntry = new \Schoolbox\External\ClassEntry();
				$classEntry->setName((string)$actualInfo['CourseName']->me['string']);
				$classEntry->setCode((string)$actualInfo['ClassCode']->me['string']);
				$classEntry->setDescription('');

				$mapper->save($classEntry);
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
			$classPeriodMapper = new \Schoolbox\External\ClassPeriodMapper();
			$userMapper = new \Schoolbox\UserMapper();

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
	static public function publishDailyData(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			$allData = $message->params[3]->me['array'];
//
//			$timetablePeriodMapper = new \Schoolbox\External\TimetablePeriodMapper();
//
//			$i = 0;
//			foreach($allData as $currentData) {
//				External_Liss_Server::log($currentData);
//				$struct = $currentData->me['struct'];
//
//				$timetablePeriod = new \Schoolbox\External\TimetablePeriod();
//				$timetablePeriod->setCycle(2);
//				$timetablePeriod->setTitle((string)$struct['Period']->me['string']);
//				$timetablePeriod->setStart((string)$struct['From']->me['string']);
//				$timetablePeriod->setFinish((string)$struct['To']->me['string']);
//
//				/* What populates this?
//				 */
//				$timetablePeriod->setDay(0);
//				$timetablePeriod->setSequence($i);
//				$timetablePeriod->setUsable(1);
//
//				$timetablePeriodMapper->save($timetablePeriod);
//
//				$i++;
//			}

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
	static public function publishCalendar(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			External_Liss_Server::log($message);

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

		External_Liss_Server::log($ttStructure);

		if ($authenticator->isAuthenticated()) {
			$allData = $message->params[2]->me['array'];

			$timetablePeriodMapper = new \Schoolbox\External\TimetablePeriodMapper();
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