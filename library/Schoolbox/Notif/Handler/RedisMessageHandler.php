<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Notif\Handler;

use Alaress\Async\Notification\Instance\Instance;
use InvalidArgumentException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Predis\ClientInterface;
use Redis;
use Schoolbox\Realtime\MessageList\IFactory;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\Realtime\Rerouter\QueueSorter;


class RedisMessageHandler extends AbstractProcessingHandler
{

    /** @var Redis|ClientInterface */
    private $redis;

    /** @var IFactory */
    private $factory;


    /**
     * @param ClientInterface $redis
     * @param IFactory        $factory
     * @param int             $level  The minimum logging level at which this handler will be triggered
     * @param bool            $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(ClientInterface $redis, IFactory $factory, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->redis   = $redis;
        $this->factory = $factory;
    }


    /**
     * Writes the record down to the log of the implementing handler
     *
     * @param  array $record
     * @return void
     */
    protected function write(array $record)
    {
        if (!preg_match('`(\w+)\.user\.(\d+)`', $record['channel'], $matches)) {
            // not a relevant message
            return;
        }

        try {
            $instance = Instance::jsonUnserialize($record['message']);
        } catch (InvalidArgumentException $e) {
            // we can't actually do anything, and there's no point letting this exception propagate
            return;
        }

        list (, $queue, $userId) = $matches;

        $publish = false;

        switch ($queue) {
            case 'listed': // TODO where does this const come from?
            case QueueSorter::TOPIC_INSTANT:
                $this->factory->unread($userId)->push($instance);
                $this->factory->listed($userId)->push($instance);
                $publish = true;
                break;

            case QueueSorter::TOPIC_DIGEST:
                $this->factory->digest($userId)->push($instance);
                break;

            default:
                // not a relevant message (this SHOULD suppress bell-times from landing in users' lists)
                return;
        }

        if ($publish) {
            $this->redis->publish(
                sprintf('msg:%s:%d:%s', INSTANCE_ID, $userId, PipeManager::PIPE_METHOD_PUSH),
                json_encode($record)
            );
        }
    }

}