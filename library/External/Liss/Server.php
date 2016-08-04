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

class External_Liss_Server {

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
			/* TODO: Assume a particular cycle
			 */
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
			$timetableCampus->setStartDay(1);

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
			'liss.getHello' => array(
				'function' => 'External_Liss_Server::getRooms'
			),
			'liss.publishStudents' => array(
				'function' => 'External_Liss_Server::publishStudents',
			),
			'liss.publishTeachers' => array(
				'function' => 'External_Liss_Server::publishTeachers',
			),
			'liss.publishBellTimes' => array(
				'function' => 'External_Liss_Server::publishBellTimes',
			),
			'liss.publishClasses' => array(
				'function' => 'External_Liss_Server::publishClasses',
			),
			'liss.publishClassMemberships' => array(
				'function' => 'External_Liss_Server::publishClassMemberships',
			),
			'liss.publishTimetable' => array(
				'function' => 'External_Liss_Server::publishTimetable',
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
	 * Publish students to the Schoolbox
	 *
	 * @param xmlrpcmsg $message
	 * @return xmlrpcresp
	 */
	static public function publishClassMemberships(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			$classInvolvementMapper = new \Schoolbox\External\ClassInvolvementMapper();
			$classEntryMapper = new \Schoolbox\External\ClassEntryMapper();
			$userMapper = new \Schoolbox\UserMapper();

			$classInvolvementMapper->truncate();

			$allData = $message->params[1]->me['array'];

			foreach($allData as $currentData) {
				$struct = $currentData->me['struct'];

				try {
					$user = $userMapper->findByExternalId(trim((string)$struct['StudentId']->me['string']));
					$class = $classEntryMapper->findOneByColumn('code', trim((string)$struct['ClassCode']->me['string']));

					$classInvolvement = new \Schoolbox\External\ClassInvolvement();
					$classInvolvement->setClass($class);
					$classInvolvement->setUser($user);
					$classInvolvementMapper->save($classInvolvement);
				}
				catch (\Exception $e) {
					External_Liss_Server::log($e->getMessage());
				}
			}

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
	static public function publishTeachers(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			$userMapper = new \Schoolbox\UserMapper();

			$allData = $message->params[2]->me['array'];

			foreach($allData as $currentData) {
				$struct = $currentData->me['struct'];

				/* Update vs create
				 * Try to find via external id - if one exists, update it
				 * Otherwise create a new one
				 */
				try {
					$externalId = (string)$struct['TeacherCode']->me['string'];

					$user = $userMapper->findByExternalId($externalId);
				}
				catch (\Exception $e) {
					$user = new \Schoolbox\User();
				}

				/* TODO: SDK doesn't match spec
				 */
				$name = (string)$struct['Name']->me['string'];
				$bits = explode(' ', $name);

				$user->setFirstname($bits[0]);
				$user->setLastname($bits[1]);
				$user->setExternalId((string)$struct['TeacherCode']->me['string']);

				/* TODO: Add campus
				 */
				$campusMapper = new \Schoolbox\CampusMapper();
				$campus = $campusMapper->findById(1);

				$campusCollection = new \Schoolbox\Collection();
				$campusCollection->add($campus);

				$user->setCampusCollection($campusCollection);
				$user->setDefaultCampus($campus);

				/* TODO: Add role
				 */
				$roleMapper = new \Schoolbox\RoleMapper();
				$role = $roleMapper->findById(2);

				$user->setRole($role);

				/* TODO: Emails
				 */
				$user->setLocalEmail(0);
				$user->setWebmail(1);
				$user->setUseInternalMail(0);

				/* TODO: Eportfolio
				 */
				$user->setEportfolioEnable(1);

				/* TODO: Mandatory other fields
				 */
				$user->setUsername('teacher' . md5($user->getFirstname() . $user->getLastname()));
				$user->setSuperuser(0);
				$user->setEnabled(1);

				$userMapper->save($user);
			}

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
	static public function publishStudents(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			$userMapper = new \Schoolbox\UserMapper();

			$allData = $message->params[2]->me['array'];

			foreach($allData as $currentData) {
				$struct = $currentData->me['struct'];

				/* Update vs create
				 * Try to find via external id - if one exists, update it
				 * Otherwise create a new one
				 */
				try {
					$externalId = (string)$struct['StudentId']->me['string'];

					$user = $userMapper->findByExternalId($externalId);
				}
				catch (\Exception $e) {
					$user = new \Schoolbox\User();
				}

				$user->setFirstname((string)$struct['FirstName']->me['string']);
				$user->setLastname((string)$struct['Surname']->me['string']);
				$user->setExternalId((string)$struct['StudentId']->me['string']);
				$user->setYear((string)$struct['Form']->me['string']);

				/* TODO: Add campus
				 */
				$campusMapper = new \Schoolbox\CampusMapper();
				$campus = $campusMapper->findById(1);

				$campusCollection = new \Schoolbox\Collection();
				$campusCollection->add($campus);

				$user->setCampusCollection($campusCollection);
				$user->setDefaultCampus($campus);

				/* TODO: Add role
				 */
				$roleMapper = new \Schoolbox\RoleMapper();
				$role = $roleMapper->findById(3);

				$user->setRole($role);

				/* TODO: Emails
				 */
				$user->setLocalEmail(0);
				$user->setWebmail(1);
				$user->setUseInternalMail(0);

				/* TODO: Eportfolio
				 */
				$user->setEportfolioEnable(1);

				/* TODO: Mandatory other fields
				 */
				$user->setUsername('student' . md5($user->getFirstname() . $user->getLastname()));
				$user->setSuperuser(0);
				$user->setEnabled(1);

				$userMapper->save($user);
			}

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
				$struct = $currentData->me['struct'];

				$classEntry = new \Schoolbox\External\ClassEntry();
				$classEntry->setName((string)$struct['CourseName']->me['string']);
				$classEntry->setCode(trim((string)$struct['ClassCode']->me['string']));
				$classEntry->setDescription('');

				$classEntryMapper->save($classEntry);
			}

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
			$defaultUser = $userMapper->findById(1); // changed to alaress user for testing

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
				$allClasses = $classEntryMapper->findByColumn('code', trim($struct['ClassCode']->me['string']));

				/* Find the Teacher
				 */
				$allTeachers = $userMapper->findByColumn('synergy_id', $struct['TeacherCode']->me['string']);

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

				/* Start a collection for a day, if it's not already a collection
				 */
				if (!($collection->getOffset($dayNumber) instanceof \Schoolbox\Collection)) {
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

			return new xmlrpcresp(new xmlrpcval('BellTimes Imported', 'string'));
		}
		return new xmlrpcresp(new xmlrpcval('Authentication Failed', 'string'));
	}
}
