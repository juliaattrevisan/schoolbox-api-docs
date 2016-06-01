<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Realtime\Test\AbstractNotificationTest;

/**
 * Test case for the test framework.
 *
 * It tests solely whether the test framework is able to listen to all daemons.
 */
class TestFramework extends AbstractNotificationTest
{
    /**
     * Set up the test case.
     */
    public function prepare()
    {
        // fear not - you are sudo
        `mysql schoolbox < /vagrant/sql/notifications-all-instant.sql 2>&1`;
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
     * When all daemons are ready, do this.
     *
     * Actions in this method will trigger other `$this->on*` methods.
     *
     * @rerouted  Added (?P<count>\d+) (?P<subscriptions>subscriptions)
     * @scheduled Connected to (?P<stomp>STOMP)
     * @digestd   Scheduling (?P<trigger>trigger)
     * @socketd   Listening for incoming (?P<websockets>websockets)
     *
     * @param int    $subscriptions
     * @param string $stomp
     * @param string $trigger
     * @param string $websockets
     * @param null   $blah
     */
    public function onAllDaemonsReady($subscriptions = null, $stomp = null, $trigger = null, $websockets = null, $blah = null)
    {
        // If I got here that means that this test is a success,
        // as long as vars are parsed properly.
        $this->log->notice("All daemons are ready", [ 'char' => '>' ]);
        $this->assertEquals('subscriptions', $subscriptions);
        $this->assertEquals('STOMP', $stomp);
        $this->assertEquals('trigger', $trigger);
        $this->assertEquals('websockets', $websockets);
        $this->assertNull($blah);

        // I should be able to signal that this is the end of the test.
        // Any on* methods that haven't been called must fail.
    }
}
