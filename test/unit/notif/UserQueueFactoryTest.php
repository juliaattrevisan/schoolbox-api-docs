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

		// yes, I just mocked out the class being unit tested. this is to release dependency on External_Synergetic_User

		$uqf = $this->getMock('Schoolbox\Notif\Queue\UserQueueFactory', array('GET_DEPRECATED_EXT_USER'), array(new \Schoolbox\Notif\MQ\Test\MockAMQPConnector($messages)));

		$uqf->expects($this->any())->method('GET_DEPRECATED_EXT_USER')->willReturn(new External_Offline_User());


		$stubCatMapper = $this->getMock('Schoolbox\Resource\CategoryMapper', array('findForUser'));
		$stubCatMapper->expects($this->any())->method('findForUser')->willReturn(new \Schoolbox\Collection());

		$stubUserMapper = $this->getMock('Schoolbox\UserMapper', array('findById', 'findByColumnList'));
		$stubUserMapper->expects($this->any())->method('findById')->willReturn($this->newUser(\Schoolbox\Role::STUDENT));
		$stubUserMapper->expects($this->any())->method('findByColumnList')->willReturn(new \Schoolbox\Collection());

		$uqf->setCategoryMapper($stubCatMapper)
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
	 * Generate a test-double User
	 *
	 * @param string $roletype
	 * @return \Schoolbox\User
	 */
	protected function newUser($roletype) {
		$role = new \Schoolbox\Role();
		$role->setType($roletype)->setId(9001);

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

}
 