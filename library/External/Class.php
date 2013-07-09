<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */
 
class External_Class implements External_ClassInterface{
	private $database;

	/**
	 * Constructor
	 *
	 * @throws Exception
	 * @param String $databaseName
	 */
	public function __construct ($databaseName = '') {
		if ( empty($databaseName) ) {
			$databaseName = Config::get('external_type');
		}

		$databaseClass = 'External_'.ucfirst($databaseName).'_Class';

		if ( class_exists($databaseClass, true) ) {
			$database = new $databaseClass;

			$this->setDatabase( $database );
		}
		else {
			error(22);
		}
	}

	public function getStudents ($classCode) {
		return $this->getDatabase()->getStudents($classCode);
	}

	public function getTeacherName ($classCode) {
		return $this->getDatabase()->getTeacherName($classCode);
	}


	public function __call ($methodName, $argumentList) {
		$database = $this->getDatabase();

		$methodReflection = new ReflectionMethod($database, $methodName);

		return $methodReflection->invokeArgs($database, $argumentList);
	}

	private function setDatabase ($database) {
		$this->database = $database;
	}

	private function getDatabase () {
		return $this->database;
	}
}