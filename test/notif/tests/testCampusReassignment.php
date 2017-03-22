<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\CampusMapper;
use Schoolbox\News\Article;
use Schoolbox\News\ArticleMapper;
use Schoolbox\Realtime\Test\AbstractNotificationTest;
use Schoolbox\Role;
use Schoolbox\RoleMapper;


/**
 * This class tests whether the rerouter respects users moving from one campus to another.
 */
class CampusReassignment extends AbstractNotificationTest
{
    const CAMPUS_ONE = 1;
    const CAMPUS_TWO = 2;

    /** @var bool */
    private $loaded = false;

    /** @var int */
    private $activeWorkers = 0;

    /** @var array */
    private $roleFilter = [ 'group_type' => Role::PARENT ];

    /** @var PDOStatement */
    private $checkCampusStmt;

    /** @var int */
    private $currentCampus;

    /** @var int */
    private $failureCount = 0;


    /**
     * Provide the list of daemons that need to be attached for this test
     *
     * @return array
     */
    public function requiredDaemons()
    {
        return [ self::DAEMON_SCHEDULE, self::DAEMON_REROUTE ];
    }


    /**
     * Perform any setup required
     *
     * @return void
     */
    public function prepare()
    {
        // fear not - you are sudo
        `mysql schoolbox < /vagrant/sql/notifications-all-instant.sql 2>&1`;
        $this->log->notice('Weapons to maximum');
        $this->log->notice(trim(`redis-cli keys "msg:vagrantx:*" | xargs redis-cli del 2>&1`) . ' redis queues cleared');
        `rabbitmqadmin get queue=vagrantx.sb.sched count=10000 requeue=false`;
        $this->log->notice("Rabbit schedule queue cleared");

        // fix email addresses
        Database::getInstance()->query("UPDATE `user` SET alt_email = CONCAT(LOWER(firstname), '.', LOWER(lastname), '@school.box')");

        $this->checkCampusStmt = Database::getInstance()->prepare("
            SELECT COUNT(*)
            FROM userCampus
            WHERE campus = ?
            AND uid = ?
        ");

        // put everyone in senior campus
        $this->moveCampus(self::CAMPUS_ONE);
    }


    /**
     * @rerouted  Added \d+ subscriptions
     * @scheduled Connected to STOMP
     */
    public function onReady()
    {
        if ($this->loaded == true) {
            return;
        }

        $this->loaded = true;
        $this->broadcast($this->roleFilter);
        $this->log->info("Published an article");
    }


    /**
     * @rerouted Tasking \d+ with reroute
     */
    public function onWorkerTasked()
    {
        ++$this->activeWorkers;
    }


    /**
     * @rerouted Task Complete
     */
    public function onWorkerComplete()
    {
        --$this->activeWorkers;

        if ($this->activeWorkers > 0) {
            $this->log->info("Waiting for $this->activeWorkers more workers to finish");
            return;

        } elseif ($this->activeWorkers < 0) {
            $this->log->error("WTF?!");
            return;

        } elseif ($this->currentCampus == self::CAMPUS_TWO) {
            // this is the end of the test

            if ($this->failureCount > 0) {
                $this->log->alert("Test ended with failures");
            } else {
                $this->log->warning("Test ended, no failures recorded; manual check for success required");
            }

            $this->terminateDaemons();
            return;
        }

        $this->log->warning("All roles processed; moving to new campus");
        $this->moveCampus(self::CAMPUS_TWO);

        // signal rerouted to drop the cache and
        // TODO implement REDIS publish
    }


    /**
     * @rerouted Dropped \d+ subscriptions
     * @rerouted Reload complete
     */
    public function onReloadComplete()
    {
        $this->log->notice("Reload complete, broadcasting another article", [ 'char' => '>' ]);
        $this->broadcast($this->roleFilter);
    }


    /**
     * @rerouted SEND (?P<messageId>[0-9a-f]{40}:[0-9a-f]{40}) -> instant.user.(?P<recipientId>\d+)
     *
     * @param string $messageId
     * @param string $recipientId
     */
    public function onReceipt($messageId, $recipientId)
    {
        try {
            $message = $this->fetchMessageInstance($messageId)->jsonSerialize();

        } catch (OutOfBoundsException $e) {
            $this->log->warning("Bad message ID: $messageId, skipping");
            return;
        }

        if (!$this->verifyCampusMembership($message, $recipientId)) {
            $this->log->error("User $recipientId should NOT have received message $messageId");
            ++$this->failureCount;
        }
    }


    /**
     * @rerouted DROP (?P<messageId>[0-9a-f]{40}:[0-9a-f]{40})
     *
     * @param string $messageId
     */
    public function onDrop($messageId)
    {

    }


    /**
     * @param array $message
     * @param int   $recipientId
     * @return bool
     */
    private function verifyCampusMembership(array $message, $recipientId)
    {
        foreach ($message['context']['campus'] as $campusId) {
            $this->checkCampusStmt->execute([ $campusId, $recipientId ]);

            if ($this->checkCampusStmt->fetchColumn()) {
                return true;
            }
        }
        return false;
    }


    /**
     * @param array $roleFilter
     * @throws \Schoolbox\Mapper\Exception
     * @return void
     */
    private function broadcast(array $roleFilter = [])
    {
        // create a news article targeted at campus:1
        $am = ArticleMapper::getInstance();

        /** @var Article $article */
        $article = clone $am->findById(1);

        // forcibly remove guests in all cases
        $roles = RoleMapper::getInstance()->findByColumnList($roleFilter)->filter(function (Role $role) { return !$role->isGuest(); });

        $this->log->info("{$roles->count()} roles to notify");

        $article->setId(null)
                ->setExpiryDate(Alaress_Date_Format::now()->addYears(100))
                ->setCampuses(CampusMapper::getInstance()->findByColumn('id', self::CAMPUS_ONE))
                ->setRoles($roles);

        $am->setSessionData([ 'user' => $article->getOwner() ]);
        $am->create($article);
    }


    /**
     * @param int $destinationId
     * @return void
     */
    private function moveCampus($destinationId)
    {
        Database::getInstance()->query("TRUNCATE `userCampus`");
        Database::getInstance()->query("INSERT INTO `userCampus` SELECT id, :c, :c FROM `user`", [ 'c' => (int)$destinationId ]);
        $this->currentCampus = $destinationId;
        $this->log->notice("Everyone is now in campus $destinationId");
    }

}