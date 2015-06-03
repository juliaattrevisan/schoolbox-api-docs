<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter;


use Alaress\Async\Stomp\SubscriptionList;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\Queue\QueueFactory;
use Schoolbox\UserMapper;
use Schoolbox\Mapper\Exception as MapperException;

class UserSubscriptionList extends SubscriptionList {

    /** @var QueueFactory */
    protected $qf;

    /** @var LoggerInterface */
    protected $log;

    /**
     * @param QueueFactory $qf
     * @param LoggerInterface $log
     */
    public function __construct(QueueFactory $qf, $log)
    {
        $this->qf = $qf;
        $this->log = $log;
    }


    /**
     * @param int $userId
     * @return string
     */
    public function createQueueName($userId)
    {
        return $this->qf->buildForUser($userId)->getName();
    }


    /**
     * @param UserMapper $source
     * @param bool       $clear
     * @return void
     */
    public function importFromSource($source, $clear = true)
    {
        if ($clear) {
            $this->log->debug("Dropping cache");
            $this->queues = array();
        }

        $userIds = $source->findAllIds();

        $count = count($userIds);

        if ($count == 0) {
            return;
        }

        $this->log->notice("Loading $count users");

        // temporal sample: 10, or 2%, whichever is larger
        $n = max(10, (int)($count / 50));

        // notify every 10%
        $dec = (int)($count / 10) ?: 1;

        // we'll do this the long way because somehow a user might have an id of 0...
        for ($i = 0, $a = microtime(true); $i < $count; ++$i) {

            // sample first $n iterations to gauge how long this is going to take
            if ($i == $n) {
                $b   = microtime(true);
                $ttl = ($b - $a) * ($count - $n) / $n;
                $adr = new \Alaress_Date_Format(time() + $ttl, 'U');
                $this->log->info(sprintf("Loading estimated to be complete ".$adr->getRelativeTime()), array('char' => '?'));
            }

            if ($i > 0 && $i % $dec == 0) {
                $this->log->debug(($i * 10 / $dec)."% loaded");
            }

            // result is explicitly false? we somehow miscounted - we're done here
            if (empty($userIds[$i])) {
                $this->log->debug("Was expecting to load $count users, but only got " . ($i + 1));
                return;
            }

            $id = $userIds[$i];

            $name = $this->createQueueName($id);

            // with clear, everyone gets reloaded; without, only new users get loaded
            if ($clear || $this->noQueue($name)) {
                try {
                    $user = $source->findById($id); // TODO srsly. this has to change.
                    $this->queues[$name] = $user;
                } catch (MapperException $e) {
                    $this->log->notice("Could not retrieve user with ID '$id'");
                }
            }
        }

        $this->log->debug("Finished loading");
    }


    /**
     * @param string $name
     * @return bool
     */
    protected function noQueue($name)
    {
        return !array_key_exists($name, $this->queues) || empty($this->queues[$name]);
    }

}