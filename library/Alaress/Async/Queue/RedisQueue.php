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

        foreach ($this->exec(sprintf("HMGET %s %s", $this->index(), implode(' ', $idList))) as $data) {

            // unwrap the formatting
            $body = json_decode($data, true);

            // attempt to unserialize the message
            $instances[] = Instance::jsonUnserialize($body['message']);
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
        return !!$this->exec(sprintf('HEXISTS %s %s:%s', $this->index(), $instance->getBaseHash(), $instance->getParamHash()));
    }


    /**
     * Request to drain the queue of its contents.
     *
     * @return void
     */
    public function drain()
    {
        // capture the ID list
        $idList = $this->fetchIdList();

        // delete the corresponding entries in the index
        if (count($idList)) {
            $this->exec(sprintf("HDEL %s %s", $this->index(), implode(' ', $idList)));
        }

        // finally delete the list
        $this->exec("DEL $this->name");
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
        $id    = $instance->getBaseHash() . ':' . $instance->getParamHash();
        $time  = $instance->getTime();
        $json  = $instance->jsonSerialize();
        $index = $this->index();

        // insert the msg body into the index
        $this->exec("HSET $index $id $json");

        // insert the reference into this queue
        $this->exec("ZADD $this->name $time $id");
    }


    /**
     * Simple wrapper to cast string to Predis interfaces
     *
     * @param string $string
     * @return mixed
     */
    protected function exec($string)
    {
        $xpl  = explode(' ', $string);
        $task = array_shift($xpl);

        $cmd = $this->redis->createCommand($task, $xpl);

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
        $list = $this->exec("ZRANGE $this->name 0 -1");
        return $list ? : array();
    }

}