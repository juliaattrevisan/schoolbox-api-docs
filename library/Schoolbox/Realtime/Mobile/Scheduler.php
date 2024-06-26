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
use Alaress\Async\Notification\Cloud\RateLimitException;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Date\IClock;
use Alaress\Jitter\IJitter;
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
class Scheduler extends AbstractScheduler implements IConsumer
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
            $this->log->warning(sprintf('Message received payload is not a %s instance: not retrying delivery', Instance::class));
            // acknowledge instead of reject looks weird here,
            // but rejecting a message ends with it being put back in the queue
            // to be processed forever.
            // I'd've guessed that reject() would put the message in a dead
            // letter queue or something, but apparently not.
            $resolver->acknowledge();
            return;
        }
        if (!is_array($context)) {
            $this->log->warning('Message received has no context, thus receiving user cannot be determined: not retrying delivery');
            $resolver->acknowledge();
            return;
        }

        // Check context is instance ID + user ID:
        // currently instance ID is unused, but will be if this daemon is ever
        // made instance-agnostic.
        $instanceId = $context[0] ?? null;
        $userId     = filter_var($context[1] ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        if (!isset($instanceId, $userId)) {
            $this->log->warning('Message received context is not a tuple (instanceId: string, userId: int): not retrying delivery');
            $resolver->acknowledge();
            return;
        }

        // All good? Attempt to send.
        $this->attemptPush($instanceId, $userId, $payload, $resolver);
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

    /**
     * Internal method for attempting push of messages to service manager.
     *
     * @param string    $instanceId
     * @param int       $userId
     * @param Instance  $payload
     * @param IResolver $resolver
     * @param int       $attemptNum
     * @return void
     */
    private function attemptPush(string $instanceId, int $userId, Instance $payload, IResolver $resolver, int $attemptNum = 1): void
    {
        $this->log->debug(
            sprintf(
                'Attempting to deliver message to instance:user %s:%d, attempt %d',
                $instanceId,
                $userId,
                $attemptNum,
            )
        );
        try {
            $this->serviceManager->push($userId, $payload);
            $resolver->acknowledge();
        } catch (RateLimitException $e) {
            // Too many attempts already: give up
            // Does this mean the message should be completely rejected?
            // I guess, but log exactly to whom delivery failed.
            if ($this->jitter->tooManyAttempts($attemptNum)) {
                $this->log->warning(
                    sprintf(
                        'Message received could not be delivered to instance:user %s:%d after %d attempts: not retrying delivery',
                        $instanceId,
                        $userId,
                        $attemptNum,
                    )
                );
                // TODO should this be acknowledged or just ignored?
                //      If it's acknowledged, it will never be sent again.
                $resolver->acknowledge();
                return;
            }

            // Otherwise add it to the timer
            $wait = $this->jitter->calculateJitter($attemptNum);
            $this->log->notice(
                sprintf(
                    'Message delivery to instance:user %s:%d is likely rate-limited (%s): waiting %dms before trying again',
                    $instanceId,
                    $userId,
                    $e->getMessage(),
                    $wait,
                )
            );
            $this->loop->addTimer(
                $wait / 1000,
                fn () => $this->attemptPush($instanceId, $userId, $payload, $resolver, $attemptNum + 1),
            );
        }
    }
}
