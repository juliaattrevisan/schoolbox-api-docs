<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Mobile;

use Alaress\Async\Broker\DuplicateResolutionException;
use Alaress\Async\Broker\IBrokerException;
use Alaress\Async\Broker\IConsumer;
use Alaress\Async\Broker\IResolver;
use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\EventListener;
use Alaress\Scheduler\AbstractScheduler;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Exception;
use PDOException;
use Schoolbox\External\Exception as ExternalException;

/**
 * The mobile app scheduler: responsible for forwarding notifications to mobile
 * apps.
 */
class Scheduler extends AbstractScheduler implements IConsumer, EventListener
{
    /**
     * @return string
     */
    protected function getMethod()
    {
        return DefaultDispatchStrategy::TASK_MOBILE;
    }

    /**
     * Handle events sent to this scheduler's signal queue.
     *
     * @param IEvent $event
     * @return void
     */
    public function handle(IEvent $event)
    {
        // noop: this is the signal queue listener
        // Maybe this will allow restart of the daemon with a signal?
    }

    /**
     * This method does nothing, as this scheduler does not use workers.
     *
     * @param $identifier
     * @param $command
     * @param $data
     * @return void
     */
    public function handleResponse($identifier, $command, $data)
    {
    }

    /**
     * Receive a message, and do something with it.
     *
     * @param            $payload
     * @param IResolver  $resolver
     * @param array|null $context
     * @return void
     */
    public function onMessage($payload, IResolver $resolver, ?array $context)
    {
        $this->log->notice(sprintf('payload: %s', json_encode($payload)));
        $this->log->notice(sprintf('resolver: %s', json_encode($resolver)));
        $this->log->notice(sprintf('context: %s', json_encode($context)));
    }

    /**
     * Exception handler: currently copied from schedule daemon
     *
     * @param Exception $e
     * @return void
     * @throws Exception
     */
    public function onException(Exception $e)
    {
        if ($e instanceof IBrokerException) {
            $msg = $e->getMessage();
            if ($e->getPrevious()) {
                $msg .= " - {$e->getPrevious()->getMessage()}";
            }

            $this->log->warning("Could not schedule: {$msg}. Dropping message.");

            try {
                // drop the message
                $e->getResolver()->acknowledge();
            } catch (DuplicateResolutionException $e) {
                // do nothing, this message was just double broken.
            }
            return;
        }

        $this->log->critical(sprintf('%s in %s:%s: "%s"', get_class($e), $e->getFile(), $e->getLine(), $e->getMessage()), ['exception' => $e]);
        // we need the daemon to crash if there's a database issue
        if ($e instanceof PDOException || $e instanceof ExternalException) {
            throw $e;
        }
    }
}
