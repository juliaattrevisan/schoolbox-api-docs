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
use Predis\Client;

class RedisQueue implements QueueInterface
{

    /** @var Client */
    protected $redis;

    /** @var string */
    protected $name;


    /**
     * @param Client $redis
     * @param string $label
     * @param int    $id
     */
    public function __construct(Client $redis, $label, $id)
    {
        $this->redis = $redis;
        $this->name  = 'q:' . INSTANCE_ID . ":$id:$label";
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

        $idList = $this->exec("ZRANGE $this->name 0 -1");

        if (!$idList) {
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

}