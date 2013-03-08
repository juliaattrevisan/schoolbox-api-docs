<?php
/**
 * Description of the file and what its purpose is
 *
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   2013 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

namespace Schoolbox\Test;
use Schoolbox\User;
use Schoolbox\TestCase;

class UserTest extends TestCase {

	/**
	 * @var User
	 */
	protected $object;

	protected $config_vars = array(
		array('site_domain', 'localhost')
	);

	public function setUp() {
		parent::setUp();
		$this->object = new User();
		$this->object->setConfigLoader($this->mock_config);
	}

	public function testLocalEmailAddress() {
		$this->object->setLocalEmail(true);
		$this->object->setUsername('testuser');
		$expected = $this->object->getUsername().'@'.$this->mock_config->get('site_domain');
		$this->assertEquals($this->object->getEmail(), $expected);
	}

	public function testAltEmailAddress() {
		$email = 'testuser@gmail.com';
		$this->object->setLocalEmail(false);
		$this->object->setUsername('testuser');
		$this->object->setAltEmail($email);
		$this->assertEquals($this->object->getEmail(), $email);
	}
}