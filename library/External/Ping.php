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
	 * Constructor
	 *
	 * @throws Exception
	 * @param String $databaseName
	 */
	public function __construct ($databaseName = '') {
		if (empty($databaseName)) {
			$databaseName = Config::get('external_type');
		}
		$databaseClass = 'External_'.ucfirst($databaseName).'_Ping';
		if ( class_exists($databaseClass, true) ) {
			try {
				$this->database = new $databaseClass();
			}
			catch(\Schoolbox\External\Exception $e) {
				$this->database(new External_Offline_Ping());
			}
		}
		else {
			$this->database = new External_Offline_Ping();
		}
	}

	public function isResponsive() {
		if (!isset($this->database)) {
			return false;
		}
		return $this->database->isResponsive();
	}
}
