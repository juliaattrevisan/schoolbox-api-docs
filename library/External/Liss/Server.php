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
	 * Constructor
	 */
	public function __construct() {
		$this->start();
	}

	/**
	 * Start the Liss server with the list of available methods
	 * and signatures here
	 */
	private function start() {
		new xmlrpc_server(array(
			'liss.getRooms' => array(
				'function' => 'Liss::getRooms'
			),
			'liss.publishRooms' => array(
				'function' => 'Liss::publishRooms',
			)
		));
	}

	/**
	 * getRooms() is required to connect to the LissTester from
	 * their SDK. Despite documentation saying that liss.hello
	 * should be the first you implement, getRooms() is actually
	 * called whenever you click Connect in the LissTester
	 *
	 * Discovered via apache mod_dumpio
	 *
	 * @return xmlrpcresp
	 */
	public function getRooms() {
		return new xmlrpcresp(new xmlrpcval('got rooms', 'string'));
	}

	/**
	 * @return xmlrpcresp
	 */
	public function publishRooms() {
		return new xmlrpcresp(new xmlrpcval('got rooms', 'string'));
	}
}