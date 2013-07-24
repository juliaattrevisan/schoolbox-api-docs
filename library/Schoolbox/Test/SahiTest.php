<?php
/**
 * My first functional test!
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2013 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\Test;
use Schoolbox\Test\TestCase;
use Behat\Mink\Driver;
use Behat\Mink\Session;

class SahiTest extends TestCase {

	/**
	 * @var Driver\DriverInterface
	 */
	protected static $driver;

	/**
	 * @var Session
	 */
	protected static $session;

	protected $config_vars = array(
		array('site_domain', 'localhost')
	);

	public static function setUpBeforeClass() {
		self::$driver  = new Driver\SahiDriver('firefox');
		self::$session = new Session(self::$driver);
		self::$session->start();
	}

	public function setUp() {
		parent::setUp();
		self::$session->reset();
	}

	public function testSomething() {
		self::$session->visit('http://192.168.36.10');
		$page = self::$session->getPage();
		$page->findById('username')->setValue('alaress');
		$page->findById('password')->setValue('alaress');
		$page->find('css', 'input.login')->press();
		$this->assertEquals(1, self::$session->evaluateScript('$j(\'a[href="/logout.php"]\').length'));
	}

	public static function tearDownAfterClass() {
		self::$session->stop();
	}
}