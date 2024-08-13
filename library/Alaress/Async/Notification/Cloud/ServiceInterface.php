<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Cloud;

use Alaress\Async\Notification\Instance\Instance;
use Psr\Log\LoggerInterface;

interface ServiceInterface
{
    /**
     * @return string
     */
    public function getProviderName();


    /**
     * @return string
     */
    public function getAppIdentifier();


    /**
     * @param array $tokens
     * @param Instance $instance
     * @param int   $unreadCount
     * @return bool
     */
    public function send(array $tokens, Instance $instance, $unreadCount = 0, ?string $recipientExternalId = null);


    /**
     * @param LoggerInterface $log
     * @return self
     */
    public function setLog(LoggerInterface $log);
}
