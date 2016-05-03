<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */

class External_Ping implements External_PingInterface {

	/**
	 * @var External_PingInterface
	 */
	protected $database;

	/**
	 * @var External_Offline_Ping
	 */
	private $offline;
	
	/**
	 * Constructor
	 *
	 * @throws Exception
	 * @param String $databaseName
	 */
	public function __construct($databaseName = '') {
		$factory = new External_DatabaseFactory();
		$this->database = $factory->buildPingConnector($databaseName);
		$this->setOffline(new External_Offline_Ping());
	}

	public function isResponsive() {
		if (!isset($this->database)) {
			return false;
		}
		return $this->database->isResponsive();
	}

	/**
	 * @return External_Offline_Ping
	 */
	public function getOffline()
	{
		return $this->offline;
	}

	/**
	 * @param External_Offline_Ping $offline
	 */
	public function setOffline($offline)
	{
		$this->offline = $offline;
	}
}
