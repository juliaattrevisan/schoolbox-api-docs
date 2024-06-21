<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Mobile;

use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\EventListener;
use Alaress\Scheduler\AbstractScheduler;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;

/**
 * The mobile app scheduler: responsible for forwarding notifications to mobile
 * apps.
 */
class Scheduler extends AbstractScheduler implements EventListener
{
    protected function getMethod()
    {
        return DefaultDispatchStrategy::TASK_MOBILE;
    }

    public function handle(IEvent $event)
    {
        // noop: this is the signal queue listener
        // Maybe this will allow restart of the daemon with a signal?
    }

    /**
     * Actually handle a response.
     *
     * @param $identifier
     * @param $command
     * @param $data
     * @return void
     */
    public function handleResponse($identifier, $command, $data)
    {
        $this->log->notice(sprintf('identifier: %s', json_encode($identifier)));
        $this->log->notice(sprintf('command: %s', json_encode($command)));
        $this->log->notice(sprintf('data: %s', json_encode($data)));
    }
}
