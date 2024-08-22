<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Cloud\Test;

use Alaress\Async\Notification\Cloud\AbstractService;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\Notification;
use Psr\Log\LoggerInterface;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\User;

/**
 * Class TestService
 *
 * Simple generic test service that propagates its content to Monolog, so it can be sent wherever
 *
 * @package Alaress\Async\Notification\Cloud\Test
 */
class TestService extends AbstractService
{
    /**
     * @inheritDoc
     */
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0, ?Instance $instance = null, ?User $recipient = null): bool
    {
        $this->getLog()->info('This is a test message: it is not sent anywhere');
        $this->getLog()->info(
            $title,
            [
                'app_tokens'   => $tokens,
                'message_id'   => $id,
                'unread_count' => $unreadCount,
                'notification' => new Notification([$instance])
            ],
        );

        return true;
    }


    /**
     * @return string
     */
    public function getProviderName()
    {
        return 'TEST';
    }
}
