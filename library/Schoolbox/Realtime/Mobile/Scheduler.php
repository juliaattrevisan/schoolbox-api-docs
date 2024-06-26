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
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Date\IClock;
use Alaress\Jitter\IJitter;
use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\EventListener;
use Alaress\Scheduler\AbstractScheduler;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Exception;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\External\Exception as ExternalException;
use Schoolbox\MobileApp\ServiceManager;

/**
 * The mobile app scheduler: responsible for forwarding notifications to mobile
 * apps.
 */
class Scheduler extends AbstractScheduler implements IConsumer, EventListener
{
    /**
     * Constructor
     *
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IClock                    $clock
     * @param ServiceManager            $serviceManager   The mobile app service manager: used to send messages to
     *                                                    mobile apps.
     * @param IJitter                   $jitter           Jitter: used to determine how many times to retry send of a
     *                                                    mobile notification, and also of how long to wait between
     *                                                    retries.
     */
    public function __construct(
        private LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        IClock $clock,
        private ServiceManager $serviceManager,
        private IJitter $jitter,
    ) {
        parent::__construct($this->loop, $dispatchStrategy, $log, $clock);
    }


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
     * @param Instance   $payload
     * @param IResolver  $resolver
     * @param array|null $context
     * @return void
     */
    public function onMessage($payload, IResolver $resolver, ?array $context)
    {
        // Basic checks: payload type, context
        if (!$payload instanceof Instance) {
            $this->log->warning(sprintf('Message received payload is not a %s instance: rejecting', Instance::class));
            $resolver->reject();
            return;
        }
        if (!is_array($context)) {
            $this->log->warning('Message received has no context, thus receiving user cannot be determined: rejecting');
            $resolver->reject();
            return;
        }

        // Check context is instance ID + user ID:
        // currently instance ID is unused, but may be if this daemon is ever
        // made instance-agnostic.
        $instanceId = $context[0] ?? null;
        $userId     = $context[1] ?? null;
        if (!isset($instanceId, $userId)) {
            $this->log->warning('Message received context is not a tuple (instanceId, userId): rejecting');
            $resolver->reject();
            return;
        }

        // Send $payload.
        // If it is not sent due to a rate limit or something:
        // * put it back on Redis, or a Rabbit delayed queue?
        // * add a timer for re-processing it
        // * if the mobile daemon is restarted: the JIT cache will rebuild it
        // Now send
        $this->serviceManager->push($userId, $payload);
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
