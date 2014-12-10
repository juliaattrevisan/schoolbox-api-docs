<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class UserQueueFactoryTest extends PHPUnit_Framework_TestCase {

	/**
	 * Generate a factory with dependencies mocked.
	 *
	 * @param \PhpAmqpLib\Message\AMQPMessage[] $messages
	 * @return \Schoolbox\Notif\Queue\UserQueueFactory
	 */
	protected function newUserQueueFactory($messages = array()) {
        $this->markTestSkipped('\\Schoolbox\\Notif\\Queue\\UserQueueFactory no longer exists');

		$uqf = new \Schoolbox\Notif\Queue\UserQueueFactory(new \Schoolbox\Notif\MQ\Test\MockAMQPConnector($messages));

		$stubCatMapper = $this->getMock('Schoolbox\Resource\CategoryMapper', array('findForUser'));
		$stubCatMapper->expects($this->any())->method('findForUser')->willReturn(new \Schoolbox\Collection());

		$stubUserMapper = $this->getMock('Schoolbox\UserMapper', array('findById', 'findByColumnList'));
		$stubUserMapper->expects($this->any())->method('findById')->willReturn($this->newUser(\Schoolbox\Role::STUDENT));
		$stubUserMapper->expects($this->any())->method('findByColumnList')->willReturn(new \Schoolbox\Collection(array(
			$this->newUser(\Schoolbox\Role::STUDENT, 9001),
			$this->newUser(\Schoolbox\Role::STUDENT, 9002),
		)));

		$stubRoleMapper = $this->getMock('Schoolbox\RoleMapper', array('findAll'));
		$stubRoleMapper->expects($this->any())->method('findAll')->willReturn(new \Schoolbox\Collection(array(
			$this->newRole(\Schoolbox\Role::STUDENT, 'Students', 9001),
			$this->newRole(\Schoolbox\Role::PARENT,  'Parents',  9002),
			$this->newRole(\Schoolbox\Role::STAFF,   'Staff',    9003),
		)));

		$stubExternalUserHandler = $this->getMock('External_Offline_User', array('getEnrolledStudents'));
		$stubExternalUserHandler->expects($this->any())->method('getEnrolledStudents')->willReturn(array(
			array('RelatedID' => 9001),
			array('RelatedID' => 9002)
		));

		$uqf->setCategoryMapper($stubCatMapper)
			->SET_DEPRECATED_EXT_USER($stubExternalUserHandler)
			->setRoleMapper($stubRoleMapper)
			->setUserMapper($stubUserMapper);

		return $uqf;
	}


	/**
	 * @param Schoolbox\User $user
	 * @param int $level
	 * @return \Schoolbox\Permission\Access\UserAccess
	 */
	protected function newUserAccess($user, $level) {
		$ua = new \Schoolbox\Permission\Access\UserAccess();
		$ua->setService(\Schoolbox\Permission\Access\UserAccess::SERVICE_HOMEPAGE)
			->setServiceIdentifier(9001)
			->setUser($user)
			->setAccessLevel($level);

		return $ua;
	}


	/**
	 * @param string $type
	 * @param string $name
	 * @param int $id
	 * @return \Schoolbox\Role
	 */
	protected function newRole($type, $name, $id) {
		$r = new \Schoolbox\Role();

		return $r->setType($type)
				->setId($id)
				->setName($name);
	}


	/**
	 * Generate a test-double User
	 *
	 * @param string $roletype
	 * @param int    $userId
	 * @return \Schoolbox\User
	 */
	protected function newUser($roletype, $userId = 9001) {
		$role = new \Schoolbox\Role();
		$role->setType($roletype)->setId($userId);

		$campus = new \Schoolbox\Campus();
		$campus->setId(9001);

		$usr = new \Schoolbox\User();
		$usr->setId(9001)
			->setRole($role)
			->setCampusCollection(new \Schoolbox\CampusCollection(array($campus)))
			->setAccessList(new \Schoolbox\Collection(array(
				$this->newUserAccess($usr, \Schoolbox\Permission\Access\UserAccess::ACCESS_DENY),
				$this->newUserAccess($usr, \Schoolbox\Permission\Access\UserAccess::ACCESS_READ),
				$this->newUserAccess($usr, \Schoolbox\Permission\Access\UserAccess::ACCESS_WRITE),
				$this->newUserAccess($usr, \Schoolbox\Permission\Access\UserAccess::ACCESS_FULL),
			)))
			->setSuperuser(true)
			->setSchoolHouse(9001)
			->setResidentialHouse(9001)
			->setExternalId(9001);

		return $usr;
	}

	protected function newAMQPMessage($msgString, $params = array(), $ns = 'mock', $icon = '#', $href = '#', $time = 0, $meta = array()) {
		return new \PhpAmqpLib\Message\AMQPMessage(json_encode(array(
			'message' => json_encode(array(
				'title' => array(
					'text' => $msgString,
					'params' => $params
				),
				'namespace' => $ns,
				'icon' => $icon,
				'href' => $href,
				'time' => $time ?: time(),
				'meta' => $meta
			))
		)));
	}


	public function testBindUserQueue() {
		$uq = $this->newUserQueueFactory()->bindUserQueue($this->newUser(\Schoolbox\Role::STUDENT));
		$this->assertInstanceOf('Schoolbox\Notif\Queue\UserQueue', $uq);
		$this->assertInternalType('array', $uq->getBindings());
	}

	public function testBindUserQueueSimulated() {
		$uq = $this->newUserQueueFactory()->bindUserQueue($this->newUser(\Schoolbox\Role::STUDENT), true);
		$this->assertInstanceOf('Schoolbox\Notif\Queue\UserQueue', $uq);
		$this->assertInternalType('array', $uq->getBindings());
	}

	public function testGenerateBindingsForStudent() {
		$bindings = $this->newUserQueueFactory()->generateBindingsForUser($this->newUser(\Schoolbox\Role::STUDENT));
		$this->assertEquals(array('student.#'), $bindings['roletype']);
	}

	public function testGenerateBindingsForParent() {
		$bindings = $this->newUserQueueFactory()->generateBindingsForUser($this->newUser(\Schoolbox\Role::PARENT));
		$this->assertEquals(array('parent.#'), $bindings['roletype']);
	}

	public function testGenerateBindingsForStaff() {
		$bindings = $this->newUserQueueFactory()->generateBindingsForUser($this->newUser(\Schoolbox\Role::STAFF));
		$this->assertEquals(array('staff.#'), $bindings['roletype']);
	}

	public function testBuildUserQueue() {
		$uq = $this->newUserQueueFactory(array(
			new \PhpAmqpLib\Message\AMQPMessage('foobar')
		))->buildUserQueue(9001, 10, false);
		$n = $uq->getNotifications();
		$this->assertCount(0, $n);
	}

	public function testDestroyUserBindings() {
		$factory = $this->newUserQueueFactory();

		$ux9001 = $factory->getMessageQueueConnector()->getSecondaryExchange(9001)->getName();
		$ux9002 = $factory->getMessageQueueConnector()->getSecondaryExchange(9002)->getName();

		$factory->setRabbitController(new \Schoolbox\Notif\MQ\Test\MockBunnyControl(array('bindings' => array(
			array(
				'destination_name' => 'ttlyfoobard',
				'routing_key'      => 'foo'
			),
			array(
				'destination_name' => $ux9001,
				'routing_key'      => 'bar'
			),
			array(
				'destination_name' => $ux9001,
				'routing_key'      => 'baz'
			),
			array(
				'destination_name' => $ux9002,
				'routing_key'      => 'bin'
			),
		))));

		$count = $factory->destroyUserBindings(9001);
		$this->assertEquals(2, $count);

		$count = $factory->destroyUserBindings(9002);
		$this->assertEquals(1, $count);

		$count = $factory->destroyUserBindings(9003);
		$this->assertEquals(0, $count);
	}


	public function testDestroyUserBindingsEmpty() {
		$factory = $this->newUserQueueFactory();
		$factory->setRabbitController(new \Schoolbox\Notif\MQ\Test\MockBunnyControl());

		$count = $factory->destroyUserBindings(9001);
		$this->assertEquals(0, $count);
	}


	public function testDestroyUserBindingsNotRoot() {
		$factory = $this->newUserQueueFactory();

		$ux9001 = $factory->getMessageQueueConnector()->getSecondaryExchange(9001)->getName();
		$ux9002 = $factory->getMessageQueueConnector()->getSecondaryExchange(9002)->getName();

		$factory->setRabbitController(new \Schoolbox\Notif\MQ\Test\MockBunnyControl(array('bindings' => array(
			array(
				'destination_name' => 'ttlyfoobard',
				'routing_key'      => 'foo'
			),
			array(
				'destination_name' => $ux9001,
				'routing_key'      => 'bar'
			),
			array(
				'destination_name' => $ux9001,
				'routing_key'      => 'baz'
			),
			array(
				'destination_name' => $ux9002,
				'routing_key'      => 'bin'
			),
		)), 1));

		$count = $factory->destroyUserBindings(9001);

		$this->assertEquals(0, $count);
	}


	/**
	 * @expectedException InvalidArgumentException
	 */
	public function testUserHasSeenBrokenMessage() {
		$factory = $this->newUserQueueFactory(array(
			new \PhpAmqpLib\Message\AMQPMessage("ttlyfoobard"),
		));

		$body = json_decode($this->newAMQPMessage("foobar")->body, true);

		$factory->userHasSeen(9001, \Alaress\Async\Notification\Instance\Instance::jsonUnserialize($body['message']));
	}

	public function testUserHasSeen() {
		$msg = $this->newAMQPMessage("foobar");

		$factory = $this->newUserQueueFactory(array(
			$this->newAMQPMessage(":foobar", array('foobar' => array('text' => 'bazbin', 'href' => '#', 'mode' => 0))),
			clone $msg
		));

		$body = json_decode($msg->body, true);

		$seen = $factory->userHasSeen(9001, \Alaress\Async\Notification\Instance\Instance::jsonUnserialize($body['message']));

		$this->assertTrue($seen);
	}

	public function testUserHasNotSeen() {
		$msg = $this->newAMQPMessage("foobar");

		$factory = $this->newUserQueueFactory(array(
			$this->newAMQPMessage(":foobar", array('foobar' => array('text' => 'bazbin', 'href' => '#', 'mode' => 0))),
		));

		$body = json_decode($msg->body, true);

		$seen = $factory->userHasSeen(9001, \Alaress\Async\Notification\Instance\Instance::jsonUnserialize($body['message']));

		$this->assertFalse($seen);
	}

}
 