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
			'liss.publishRooms' => array(
				'function' => 'External_Liss_Server::publishRooms',
			)
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
		External_Liss_Server::log($message);

		return new xmlrpcresp(new xmlrpcval('got classes', 'string'));
	}

	/**
	 * Publish rooms to the Schoolbox
	 *
	 * @param xmlrpcmsg $message
	 * @return xmlrpcresp
	 */
	public function publishRooms(xmlrpcmsg $message) {
		return new xmlrpcresp(new xmlrpcval('got rooms', 'string'));
	}
}