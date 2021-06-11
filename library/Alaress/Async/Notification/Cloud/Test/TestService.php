<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Cloud\Test;

use Alaress\Async\Notification\Cloud\AbstractService;
use Psr\Log\LoggerInterface;
use Schoolbox\MobileApp\RegistryMapper;

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
     * @param array  $tokens
     * @param int    $id
     * @param string $title
     * @param int    $unreadCount
     * @return bool
     */
    protected function propagate(array $tokens, $id, $title, $unreadCount = 0)
    {
        $this->getLog()->debug('This is a test message: it is not sent anywhere');
        $this->getLog()->debug(
            $title,
            [
                'app_tokens'   => $tokens,
                'message_id'   => $id,
                'unread_count' => $unreadCount
            ]
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
