<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Notif\Handler;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Queue\RedisQueue;
use Alaress\Async\UserQueue\UserQueue;
use InvalidArgumentException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Predis\ClientInterface;
use Redis;

class RedisMessageHandler extends AbstractProcessingHandler
{

    /** @var Redis|ClientInterface */
    private $redis;


    /**
     * @param ClientInterface $redis
     * @param int             $level  The minimum logging level at which this handler will be triggered
     * @param bool            $bubble Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(ClientInterface $redis, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->redis = $redis;
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

        switch ($queue) {
            case 'listed':
            case 'instant':
                $lists = [ 'unread', 'listed' ];
                break;
            case 'digest':
                $lists = [ 'digest' ];
                break;
            default:
                // not a relevant message (this SHOULD suppress bell-times from landing in users' lists)
                return;
        }

        foreach ($lists as $list) {
            // push onto each list
            (new RedisQueue($this->redis, "$userId:$list"))->push($instance);
        }
    }

}