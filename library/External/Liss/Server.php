<?php
/**
 * External_Liss_Server
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
	public function publishClasses(xmlrpcmsg $message) {
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
	public function publishTimetable(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			$classPeriodMapper = new \Schoolbox\External\ClassPeriodMapper();
			$classEntryMapper =  new \Schoolbox\External\ClassEntryMapper();

			$allData = $message->params[1]->me['array'];

			foreach($allData as $currentData) {
				$struct = $currentData->me['struct'];

				$classPeriod = new \Schoolbox\External\ClassPeriod();
//				External_Liss_Server::log($struct);
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
	public function publishDailyData(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			$allData = $message->params[3]->me['array'];

			$timetablePeriodMapper = new \Schoolbox\External\TimetablePeriodMapper();

			$i = 0;
			foreach($allData as $currentData) {
				External_Liss_Server::log($currentData);
				$struct = $currentData->me['struct'];

				$timetablePeriod = new \Schoolbox\External\TimetablePeriod();
				$timetablePeriod->setCycle(2);
				$timetablePeriod->setTitle((string)$struct['Period']->me['string']);
				$timetablePeriod->setStart((string)$struct['From']->me['string']);
				$timetablePeriod->setFinish((string)$struct['To']->me['string']);

				/* What populates this?
				 */
				$timetablePeriod->setDay(0);
				$timetablePeriod->setSequence($i);
				$timetablePeriod->setUsable(1);

				$timetablePeriodMapper->save($timetablePeriod);

				$i++;
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
	public function publishCalendar(xmlrpcmsg $message) {
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
	public function publishBellTimes(xmlrpcmsg $message) {
		$authenticator = new External_Liss_Authenticator($message->params[0]);

		if ($authenticator->isAuthenticated()) {
			External_Liss_Server::log($message);

			return new xmlrpcresp(new xmlrpcval('Something imported', 'string'));
		}

		return new xmlrpcresp(new xmlrpcval('Not authenticated', 'string'));
	}
}