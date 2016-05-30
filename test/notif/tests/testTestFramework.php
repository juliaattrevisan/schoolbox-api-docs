<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Mapper;
use Schoolbox\Realtime\Message;
use Schoolbox\Realtime\Test\AbstractNotificationTest;
use Schoolbox\Test\TestMapper;
use Schoolbox\Test\TestModel;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Test case for the test framework.
 *
 * It tests solely whether the test framework is able to listen to all daemons.
 */
class TestFramework extends AbstractNotificationTest
{
    /**
     * User ID for a superuser.
     */
    const USERID_SUPERUSER = 19;

    /**
     * User ID for a staff member.
     */
    const USERID_ROLE_STAFF = 151;

    /**
     * User ID for a student.
     */
    const USERID_ROLE_STUDENT = 17;

    /**
     * User ID for a parent.
     */
    const USERID_ROLE_PARENT = 20;

    /**
     * @var User[]
     */
    protected $userCache;

    /**
     * Set up the test case.
     */
    public function prepare()
    {
        // fear not - you are sudo
        `mysql schoolbox < /vagrant/sql/notifications-all-instant.sql 2>&1`;
        `mysql schoolbox < /vagrant/sql/test/test-notifier.sql 2>&1`;
        $this->log->notice('Weapons to maximum');
        $this->log->notice(trim(`redis-cli keys "msg:vagrantx:*" | xargs redis-cli del 2>&1`) . ' redis queues cleared');
        `rabbitmqadmin get queue=vagrantx.sb.sched count=10000 requeue=false`;
        $this->log->notice("Rabbit schedule queue cleared");
    }

    /**
     * This test tests all daemons (I think).
     *
     * @return array
     */
    public function requiredDaemons()
    {
        return [
            self::DAEMON_SCHEDULE,
            self::DAEMON_REROUTE,
            self::DAEMON_DIGEST,
            self::DAEMON_SOCKET,
        ];
    }

    /**
     * @param int $id
     * @return User
     */
    protected function getUser($id)
    {
        if (empty($this->userCache[$id])) {
            $this->userCache[$id] = UserMapper::getInstance()->findById($id);
        }
        return $this->userCache[$id];
    }

    /**
     * When all daemons are ready, do this.
     *
     * Actions in this method will trigger other `$this->on*` methods.
     *
     * @rerouted  Added (?P<count>\d+) subscriptions
     * @scheduled Connected to STOMP
     * @digestd   Scheduling trigger
     * @socketd   Listening for incoming websockets
     */
    public function onAllDaemonsReady()
    {
        // If I got here that means that this test is a success.
        $this->log->notice("All daemons are ready", [ 'char' => '>' ]);

        $testMapper = TestMapper::getInstance();
        $testMapper->setSessionData([
            'user' => UserMapper::getInstance()->findById(self::USERID_SUPERUSER),
        ]);

        // Teachers will be subscribed to instant notifications
        // Students will be subscribed to digest notifications
        $staffTest = new TestModel();
        $staffTest->setFilename('staff.txt');
        $staffTest->setUser(UserMapper::getInstance()->findById(self::USERID_ROLE_STAFF));
        $studentTest = new TestModel();
        $studentTest->setFilename('student.txt');
        $studentTest->setUser(UserMapper::getInstance()->findById(self::USERID_ROLE_STUDENT));
        $testMapper->save([$staffTest, $studentTest]);

//        // Update the digest for students to notify in 5 seconds,
//        // so that the digest daemon triggers
//        /** @var \Schoolbox\Role $role */
//        $now  = Alaress_Date_Format::now()->addMinutes(1);
//        $role = \Schoolbox\RoleMapper::getInstance()->findById(2);
//        $role->setDigestSchedule($now->getUsingFormat('i H * * *'));
//        \Schoolbox\RoleMapper::getInstance()->save($role);

        // I should be able to signal that this is the end of the test.
        // Any on* methods that haven't been called must fail.
    }

    /**
     * @scheduled notify.user.(?P<recipientId>\d+).test.(?P<itemId>\d+).notified
     */
    public function onTestScheduleDaemon($recipientId, $itemId)
    {
        $this->log->notice("onTestScheduleDaemon triggered successfully", [ 'char' => '<' ]);
    }

    /**
     * @rerouted ^SEND (?P<messageId>[0-9a-f]{40}:[0-9a-f]{40}) -> instant.user.(?P<userId>\d+)
     *
     * @param string $messageId
     * @param int    $userId
     */
    public function onTestRerouteDaemon($messageId, $userId)
    {
        return;
//        // TODO the below doesn't quite work
//
//        try {
//            $message = $this->fetchMessageInstance($messageId)->jsonSerialize();
//        } catch (OutOfBoundsException $e) {
//            $this->log->error($e->getMessage(), [$e]);
//            return;
//        }
    }

    /**
     * @socketd notify.user.(?P<recipientId>\d+).test.(?P<itemId>\d+).notified
     * @socketd A test notification has been made
     */
    public function onTestSocketDaemon($recipientId, $itemId)
    {
        $this->log->notice("onTestSocketDaemon triggered successfully", [ 'char' => '<' ]);
    }

    /**
     * @digestd Nothing should match this yet
     */
    public function onTestDigestDaemon()
    {
    }

    /**
     * @belld Nothing should match this yet
     */
    public function onTestBellDaemon()
    {
    }
}
