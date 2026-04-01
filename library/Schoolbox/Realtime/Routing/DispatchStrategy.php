<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Routing;

use Alaress\WorkerCrew\Pooling\DispatchStrategy as BaseDispatchStrategy;

class DispatchStrategy extends BaseDispatchStrategy
{
   public const string TASK_ROUTE = 'route';

    /**
     * @return string
     */
    public function getCommand(): string
    {
        return SITE_root . "/tools/notif/route/worker.php";
    }


    /**
     * @return array
     */
    public function getSupportedActions(): array
    {
        return [ self::TASK_ROUTE ];
    }
}
