<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif;

use Psr\Log\LoggerInterface;
use Schoolbox\Resource\Storage;

interface CacheableLoggerInterface extends LoggerInterface
{
    /**
     * Flush your internal cache to storage
     *
     * @param string                    $basename
     * @param \Alaress_Date_Format|null $expiryAt
     * @return Storage\Model
     */
    public function flushToStorage(string $basename = '', \Alaress_Date_Format|null $expiryAt = null): Storage\Model;
}
