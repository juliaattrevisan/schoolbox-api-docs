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
use Schoolbox\ConfigLoader;
use Schoolbox\IConfigLoader;

class DAV_ExceptionPlugin extends ServerPlugin {

	/**
	 * @var IConfigLoader
	 */
	protected $config_loader;

	/**
	 * Subscribes to "exception" event on server.
	 *
	 * @param Server $server
	 */
	public function initialize(Server $server) {
		$this->setConfigLoader(new ConfigLoader());
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
		$fp = fopen($this->getConfigLoader()->get('logs_dir').'/dav.err', 'ab');
		fwrite($fp, sprintf("[%s] %s\n", date("Y-m-d H:i:s"), $exception->getMessage()));
		fclose($fp);
		return true;
	}

	/**
	 * @param  \Schoolbox\IConfigLoader $config_loader
	 *
	 * @return $this
	 */
	public function setConfigLoader($config_loader) {
		$this->config_loader = $config_loader;
		return $this;
	}

	/**
	 * @return \Schoolbox\IConfigLoader
	 */
	public function getConfigLoader() {
		return $this->config_loader;
	}
}