<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Test\Redis\Async;

use Alaress\Redis;
use Psr\Log\LoggerInterface;

class TestListener implements Redis\Async\EventListener
{
    /** @var LoggerInterface */
    private $log;

    /**
     * @param LoggerInterface $log
     */
    public function __construct(LoggerInterface $log)
    {
        $this->log = $log;
    }

    /**
     * @param Redis\Async\Event\Event $event
     * @return void
     */
    public function handle(Redis\Async\Event\Event $event)
    {
        $signal = strtoupper($event->getPayload());

        $known = [
            'SIGHUP'  => SIGHUP,
            'SIGPOLL' => SIGPOLL,
            'SIGINT'  => SIGINT,
            'SIGTERM' => SIGTERM,
        ];

        if (isset($known[$signal])) {
            $this->log->info("Received request to $signal, behaving as though signal received");

        } else {
            $this->log->info("Unrecognised signal '$signal'");
        }

    }
}
