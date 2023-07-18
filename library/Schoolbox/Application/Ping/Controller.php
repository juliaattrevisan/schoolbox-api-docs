<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Ping;

use Alaress\Redis;
use Alaress\Web\Controller\Base;
use Schoolbox\Config\ConfigMapper;

class Controller extends Base
{
    /**
     * Schoolbox Ping, confirm the system is functioning
     * @api   GET /ping
     * @since 23.0
     *
     */
    public function getPing(): void
    {
        // check if system dead, these connections are required for schoolbox to function
        try {
            $storage = Redis::getStorageClientInstance();
            $storage->ping();
            $cache = Redis::getCacheClientInstance();
            $cache->ping();
        } catch (\Throwable $e) {
            $this->response = $this->responseEmpty(500);
            return;
        }
        $this->response = $this->responseEmpty(200);
    }
}
