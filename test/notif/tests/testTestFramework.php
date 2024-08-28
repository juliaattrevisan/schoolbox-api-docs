<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Realtime\Test\AbstractNotificationTestCase;

/**
 * Test case for the test framework.
 *
 * It tests solely whether the test framework is able to listen to all daemons.
 */
class TestFramework extends AbstractNotificationTestCase
{
    /**
     * The test's completion is not handled by a task daemon expiring.
     *
     * @return false
     */
    protected function isTestComplete()
    {
        return false;
    }

    /**
     * This test has no stages.
     *
     * @param int $stage
     */
    protected function goToStage($stage)
    {
    }

    /**
     * Set up the test case.
     */
    public function prepare()
    {
        // fear not - you are sudo
        `mysql schoolbox < /usr/share/schoolbox/sql/notifications-all-instant.sql 2>&1`;
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
            self::DAEMON_DIGEST,
            self::DAEMON_SOCKET,
        ];
    }

    /**
     * When all daemons are ready, do this.
     *
     * Actions in this method will trigger other `$this->on*` methods.
     *
     * @routed    \d+ routes (?P<installed>installed) from cache
     * @scheduled Connected to (?P<stomp>STOMP)
     * @digestd   Scheduling (?P<trigger>trigger)
     * @socketd   Listening for incoming (?P<websockets>websockets)
     *
     * @param string $installed
     * @param string $stomp
     * @param string $trigger
     * @param string $websockets
     * @param null   $blah
     */
    public function onAllDaemonsReady($installed = null, $stomp = null, $trigger = null, $websockets = null, $blah = null)
    {
        // If I got here that means that this test is a success,
        // as long as vars are parsed properly.
        $this->log->notice('All daemons are ready', [ 'char' => '>' ]);
        $this->assertEquals('installed', $installed);
        $this->assertEquals('STOMP', $stomp);
        $this->assertEquals('trigger', $trigger);
        $this->assertEquals('websockets', $websockets);
        $this->assertNull($blah);

        // I should be able to signal that this is the end of the test.
        // Any on* methods that haven't been called must fail.
        $this->markTestComplete();
    }
}
