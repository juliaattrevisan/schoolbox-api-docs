<?php
/**
 * Plugin which puts a message in temp when an uncaught exception gets thrown.
 *
 * Use this when trying to debug issues with non-web clients.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

class ExceptionPlugin extends ServerPlugin {

	/**
	 * Subscribes to "exception" event on server.
	 *
	 * @param Server $server
	 */
	public function initialize(Server $server) {
		$server->subscribeEvent('exception', array($this, 'onException'), 10);
	}

	/**
	 * Writes a temp file containing exception message in the temp dir. It has
	 * the name "exc*".
	 *
	 * @param Exception $exception
	 *
	 * @return bool Always true - doesn't short-circuit other subscribed events.
	 */
	public function onException(Exception $exception) {
		$temp_file = tempnam(sys_get_temp_dir(), 'exc');
		file_put_contents($temp_file, $exception->getMessage());
		return true;
	}
}