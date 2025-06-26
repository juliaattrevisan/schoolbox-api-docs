<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\Mobile;

use Alaress\Async\Broker\IConsumer;
use Alaress\Async\Broker\IResolver;
use Alaress\Async\Notification\Cloud\RateLimitException;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Date\IClock;
use Alaress\Jitter\IJitter;
use Alaress\Redis\Async\Event\IEvent;
use Alaress\Redis\Async\Event\Message;
use Alaress\Redis\Async\EventListener;
use Alaress\Scheduler\AbstractScheduler;
use Alaress\WorkerCrew\Pooling\DefaultDispatchStrategy;
use Alaress\WorkerCrew\Pooling\DispatchStrategyInterface;
use Alaress_Date_Format;
use DateTime;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception;
use Schoolbox\MobileApp\ServiceManager;

/**
 * The mobile app scheduler: responsible for forwarding notifications to mobile
 * apps.
 */
class Scheduler extends AbstractScheduler implements IConsumer, EventListener
{
    /**
     * Is the blackout setting enabled?
     *
     * This controls the times during which mobile app notifications are sent:
     * if this is disabled, notifications may be sent at any time.
     */
    private bool $blackoutEnabled = false;

    /**
     * If this is set, and `$this->blackoutEnabled`: notifications won't be sent
     * after this time each day.
     */
    private ?string $blackoutStartTime = null;

    /**
     * If this is set, and `$this->blackoutEnabled`: notifications scheduled to
     * be sent after `$this->blackoutStartTime` will be sent at this time.
     *
     * If this is before `$this->blackoutStartTime`, this will be interpreted as
     * sending the notification at this time the next day.
     */
    private ?string $blackoutEndTime = null;

    /**
     * Constructor
     *
     * @param LoopInterface             $loop
     * @param DispatchStrategyInterface $dispatchStrategy
     * @param LoggerInterface           $log
     * @param IClock                    $clock
     * @param ServiceManager            $serviceManager    The mobile app service manager: used to send messages to
     *                                                     mobile apps.
     * @param IJitter                   $jitter            Jitter: used to determine how many times to retry send of a
     *                                                     mobile notification, and also of how long to wait between
     *                                                     retries.
     * @param IConfigLoader             $config            Config loader: used to load blackout settings.
     *                                                     These settings may be changed externally: in that case, the
     *                                                     config will be reloaded.
     */
    public function __construct(
        private LoopInterface $loop,
        DispatchStrategyInterface $dispatchStrategy,
        LoggerInterface $log,
        IClock $clock,
        private ServiceManager $serviceManager,
        private IJitter $jitter,
        private IConfigLoader $config,
    ) {
        parent::__construct($this->loop, $dispatchStrategy, $log, $clock);
        $this->log->notice('Loading blackout period settings');
        $this->loadBlackoutSettings();
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
            $this->log->warning(
                sprintf(
                    'Message received payload is not a %s (received: %s): not retrying delivery',
                    Instance::class,
                    is_object($payload) ? get_class($payload) : gettype($payload),
                ),
            );
            // acknowledge instead of reject looks weird here,
            // but rejecting a message ends with it being put back in the queue
            // to be processed forever.
            // I'd've guessed that reject() would put the message in a dead
            // letter queue or something, but apparently not.
            $resolver->acknowledge();
            return;
        }

        $this->log->info(
            sprintf(
                'Received message (hash: %s:%s, topic: %s)',
                $payload->getBaseHash(),
                $payload->getParamHash(),
                !empty($payload->getTopics()) ? $payload->getTopics()[0] : '',
            )
        );

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
     * Handle the given redis event
     *
     * @param IEvent $event
     * @return void
     */
    public function handle(IEvent $event)
    {
        if (!$event instanceof Message) {
            // ignore pings
            return;
        }

        // comes in the form 'mobiled.vagrantx.reload'
        [, , $action] = explode('.', $event->getChannel());
        if ($action === 'refreshConfiguration') {
            $this->log->notice('refreshConfiguration signal received: reloading blackout period settings');
            $this->config->dropCache();
            $this->loadBlackoutSettings();
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
        $this->log->info(
            sprintf(
                'Attempting to deliver message %s:%s to instance:user %s:%d, attempt %d',
                $payload->getBaseHash(),
                $payload->getParamHash(),
                $instanceId,
                $userId,
                $attemptNum,
            )
        );

        // Delay delivery
        if ($this->blackoutEnabled && $this->blackoutStartTime && $this->blackoutEndTime) {
            if ($payload->retrieveDeliveryMethod() === Instance::DELIVERY_ALERT) {
                $this->log->notice(
                    sprintf(
                        'Message %s:%s delivery type is alert: not attempting to delay',
                        $payload->getBaseHash(),
                        $payload->getParamHash(),
                    )
                );
            } else {
                /* generate the delay or null if not in quiet period
                 */
                // Is the current time within the quiet period?
                $now         = Alaress_Date_Format::now();
                $currentTime = $now->getUsingFormat('H:i');

                [$delay, $jitter, $delayTimestamp] = (new DelayGen())->getQuitePeriod(
                    $this->blackoutStartTime,
                    $this->blackoutEndTime,
                    $currentTime
                );

                if ($delay) {
                    $this->log->notice(
                        sprintf(
                            'Message %s:%s delivery delayed until %ss after end of quiet period (%s-%s) (exact time: %s) (delaying: %s seconds)',
                            $payload->getBaseHash(),
                            $payload->getParamHash(),
                            $jitter,
                            $this->blackoutStartTime,
                            $this->blackoutEndTime,
                            Alaress_Date_Format::newFromTimestamp($delayTimestamp)->toRfc3339String(),
                            $delay
                        )
                    );

                    $this->loop->addTimer(
                        $delay,
                        fn () => $this->attemptPush($instanceId, $userId, $payload, $resolver, 0),
                    );
                    return;
                }
            }
        }

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
                        'Message %s:%s could not be delivered to instance:user %s:%d after %d attempts: not retrying delivery',
                        $payload->getBaseHash(),
                        $payload->getParamHash(),
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
            $wait = -1;
            if ($e->getRetryAfter()) {
                // retry after should be after current time
                $this->log->notice(
                    sprintf(
                        'Retry time of %s provided in rate limit exception: retrying then',
                        $e->getRetryAfter()->format(DateTimeInterface::RFC3339),
                    )
                );
                $diff = $e->getRetryAfter()->diff(new DateTime());
                $wait = ($diff->s + $diff->f) * 1000;
                if ($wait < 0) {
                    $this->log->notice('Retry time before current time: using standard exponential backoff instead');
                }
            }

            if ($wait < 0) {
                $wait = $this->jitter->calculateJitter($attemptNum);
            }
            $this->log->notice(
                sprintf(
                    'Delivery of message %s:%s to instance:user %s:%d is likely rate-limited (%s): waiting %dms before trying again',
                    $payload->getBaseHash(),
                    $payload->getParamHash(),
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
        } catch (Exception $e) {
            $this->log->warning(
                sprintf(
                    'Delivery of message %s:%s to instance:user %s:%d: Mapper exception: %s',
                    $payload->getBaseHash(),
                    $payload->getParamHash(),
                    $instanceId,
                    $userId,
                    $e->getMessage()
                )
            );
            // Acknowledge the message,
            // we should be able to discard the message, it will never be sent again.
            $resolver->acknowledge();
            return;
        }
    }

    /**
     * Load blackout settings from config
     *
     * @return void
     */
    private function loadBlackoutSettings(): void
    {
        $blackoutEnabled   = (bool)$this->config->get(ConfigMapper::NAME_NOTIFICATION_BLACKOUT_ENABLED);
        $blackoutStartTime = $this->config->get(ConfigMapper::NAME_NOTIFICATION_BLACKOUT_START_TIME);
        $blackoutEndTime   = $this->config->get(ConfigMapper::NAME_NOTIFICATION_BLACKOUT_END_TIME);

        if ($blackoutEnabled) {
            // Validate blackout start and end, clearing if invalid
            if (!$this->validateTimeValue($blackoutStartTime)) {
                $this->log->warning(
                    sprintf(
                        'Blackout start %s must be a HH:MM time: disabling blackout period',
                        $blackoutStartTime
                    ),
                );
                $blackoutStartTime = null;
            }
            if (!$this->validateTimeValue($blackoutEndTime)) {
                $this->log->warning(
                    sprintf(
                        'Blackout end value %s must be a HH:MM time: disabling blackout period',
                        $blackoutEndTime
                    ),
                );
                $blackoutEndTime = null;
            }

            // If one of the above steps cleared blackout start or end,
            // ensure both are cleared
            if (!isset($blackoutStartTime, $blackoutEndTime)) {
                $blackoutStartTime = null;
                $blackoutEndTime   = null;
            } else {
                $this->log->notice(
                    sprintf(
                        'Blackout period enabled (start: %s, end: %s%s)',
                        $blackoutStartTime,
                        $blackoutEndTime,
                        $blackoutEndTime < $blackoutStartTime ? ' next day' : '',
                    ),
                );
            }
        } else {
            $this->log->notice('Blackout period not enabled');
            $blackoutStartTime = null;
            $blackoutEndTime   = null;
        }

        $this->blackoutEnabled   = $blackoutEnabled;
        $this->blackoutStartTime = $blackoutStartTime;
        $this->blackoutEndTime   = $blackoutEndTime;
    }

    /**
     * Validates `$value` is a `HH:MM` time.
     *
     * @param string $value
     * @return bool
     */
    private function validateTimeValue(string $value): bool
    {
        if (!preg_match('/^(?P<hour>\d+):(?P<minute>\d+)$/', $value, $matches)) {
            return false;
        }
        $hour   = intval($matches['hour']);
        $minute = intval($matches['minute']);
        return ($hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59);
    }
}
