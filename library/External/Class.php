<?php
/**
 * Created by JetBrains PhpStorm.
 * User: aetworowsky
 * Date: 20/10/11
 * Time: 4:40 PM
 * To change this template use File | Settings | File Templates.
 */

use Alaress\Redis;

class External_Class implements External_ClassInterface{
    use External_CacheTrait;

	private $database;

	/**
	 * @var External_Offline_Class
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
		$this->setDatabase($factory->buildClassConnector($databaseName));
		$this->setOffline(new External_Offline_Class());
		$this->setCache(Redis::getInstance());
        $this->setTimeout(Config::get('external_cache_timeout'));
	}

    public function getParents($subCode) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
    }

    public function getStudents($classCode)
    {
        $result = $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
        /* Sort by lastname then by firstname
         */
        if (is_array($result)) {
            usort(
                $result,
                function ($a, $b) {
                    $last = strnatcasecmp($a['lastname'], $b['lastname']);
                    if ($last == 0) {
                        return strnatcasecmp($a['firstname'], $b['firstname']);
                    }
                    return $last;
                }
            );
        }
        return $result;
    }

	public function getTeacherName ($classCode) {
        return $this->getCachedResult(__FUNCTION__, func_get_args(), $this->getTimeout());
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
	
	/**
	 * @return External_Offline_Class
	 */
	public function getOffline()
	{
		return $this->offline;
	}

	/**
	 * @param External_Offline_Class $offline
	 */
	public function setOffline($offline)
	{
		$this->offline = $offline;
	}
}