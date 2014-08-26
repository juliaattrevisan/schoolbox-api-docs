<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Queue;


use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;

class RedisQueue implements QueueInterface
{

    const TTL = 1209600; // 2 weeks

    /** @var ClientInterface */
    protected $redis;

    /** @var string */
    protected $name;


    /**
     * @param ClientInterface $redis
     * @param string $name
     */
    public function __construct(ClientInterface $redis, $name)
    {
        $this->redis = $redis;
        $this->name  = $name;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     *
     * @return Instance[]
     */
    public function fetch()
    {
        $instances = array();

        $idList = $this->fetchIdList();

        if (empty($idList)) {
            return $instances;
        }

        $result = $this->exec("MGET", $idList);

        foreach ($result as $data) {
            // attempt to unserialize the message
            $instances[] = Instance::jsonUnserialize(gzuncompress($data));
        }

        return $instances;
    }


    /**
     * Returns whether or not this queue contains the given instance
     *
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance)
    {
        $index = $this->index();
        $hash  = $this->getFullHash($instance);
        $queue = "$index:$this->name";
        $id    = "$index:$hash";

        $exists = !!$this->exec('EXISTS', $id);
        $listed = !!$this->exec('SISMEMBER', $queue, $id);

        // a little housekeeping: if the 'queue' contains the reference, but the msg has expired, drop the reference
        if ($listed && !$exists) {
            $this->exec('SREM', $queue, $id);
        }

        return $exists && $listed;
    }


    /**
     * Request to drain the queue of its contents.
     *
     * @return void
     */
    public function drain()
    {
        // delete the list, which is a user's reference to the messages. they will be expired automatically (and other users may still be attached)
        $this->exec("DEL", $this->index() . ':' . $this->name);
    }


    /**
     * Push a message instance into this queue
     * Note: this isn't an interfaced method; AMQP doesn't support this concept
     *
     * @param Instance $instance
     * @return void
     */
    public function push(Instance $instance)
    {
        $id    = $this->getFullHash($instance);
        $index = $this->index();
        $data  = gzcompress("$instance", 9);

        // insert the msg body into storage, with expiry automatically set
        $this->exec("SET", "$index:$id", $data);
        $this->exec("EXPIRE", "$index:$id", self::TTL);

        // insert the msg reference into this 'queue'
        $this->exec("SADD", "$index:$this->name", "$index:$id");
    }


    /**
     * Simple wrapper to encapsulate Predis interfaces
     *
     * @param string $cmd
     * @param mixed $_args
     * @return mixed
     */
    protected function exec($cmd, $_args = null)
    {
        // if the args just got put in this var, use that; otherwise, read func_args
        if (!is_array($_args)) {
            $_args = func_get_args();
            array_shift($_args);
        }

        $cmd = $this->redis->createCommand($cmd, $_args);

        return $this->redis->executeCommand($cmd);
    }


    /**
     * Returns the index hash name
     *
     * @return string
     */
    protected function index()
    {
        return "msgs:" . INSTANCE_ID;
    }


    /**
     * @return array
     */
    protected function fetchIdList()
    {
        return $this->exec("SMEMBERS", $this->index() . ':' . $this->name) ? : array();
    }


    /**
     * @param Instance $instance
     * @return string
     */
    protected function getFullHash(Instance $instance)
    {
        return 'm:' . $instance->getBaseHash() . ':' . $instance->getParamHash();
    }

}