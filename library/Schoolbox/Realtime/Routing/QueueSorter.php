<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\Async\Notification\Cloud\RateLimitException;
use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Queue\QueueInterface;
use Alaress\Jitter\IJitter;
use Exception;
use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\MessageList;
use Schoolbox\Realtime\Routing\Filter\FilterException;
use Schoolbox\Realtime\Routing\Filter\FilterInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Symfony\Component\Mime\Exception\RfcComplianceException;

class QueueSorter implements FilterInterface
{
    /**
     * @param FilterInterface                 $filter      Required for validating if a message can be sorted by this?
     * @param LoggerInterface                 $log         Logger
     * @param MessageList\IPublicationFactory $listFactory Required for building queues.
     * @param RoutePermissionMapper           $permMapper  Required for determining a message instance's routing levels.
     * @param LoopInterface                   $loop        Required for retry if rate limited.
     * @param IJitter $jitter                              Required for calculating time until retry:
     *                                                     expected to return a number of milliseconds to wait.
     */
    public function __construct(
        private FilterInterface $filter,
        private LoggerInterface $log,
        private MessageList\IPublicationFactory $listFactory,
        private RoutePermissionMapper $permMapper,
        private LoopInterface $loop,
        private IJitter $jitter,
    ) {
    }


    /**
     * Process a message for a missing user.
     *
     * This currently does nothing besides raise a log message that the given
     * user ID is missing.
     *
     * @param int      $userId
     * @param Instance $instance
     */
    public function processForMissingUser(int $userId, Instance $instance): void
    {
        $this->log->notice(
            sprintf(
                'DROP %s:%s -> user %d: user does not exist or was deleted',
                $instance->getBaseHash(),
                $instance->getParamHash(),
                $userId
            )
        );
    }


    /**
     * @param User     $user
     * @param Instance $instance
     * @return bool
     * @throws PDOException
     * @throws InvalidArgumentException
     */
    public function process(User $user, Instance $instance): bool
    {
        $userId = $user->getId();
        try {
            $this->validate($instance, $user);
            $outputLevels = $this->getReroutedLevel($user, $instance);
            if ($this->permMapper->isMuted($outputLevels)) {
                throw new FilterException('Reroute level is zero');
            }
        } catch (FilterException $e) {
            $this->log->notice(
                sprintf(
                    'DROP %s:%s (%s) -> user %d: %s',
                    $instance->getBaseHash(),
                    $instance->getParamHash(),
                    !empty($instance->getTopics()) ? $instance->getTopics()[0] : '',
                    $userId,
                    $e->getMessage()
                )
            );
            return false;
        }

        $this->log->info(
            sprintf(
                'SEND %s:%s (%s) -> user %d @ %s',
                $instance->getBaseHash(),
                $instance->getParamHash(),
                !empty($instance->getTopics()) ? $instance->getTopics()[0] : '',
                $userId,
                json_encode($outputLevels)
            )
        );

        // set true, if any of levels is processed
        $processed = false;

        // push for each output level
        foreach ($outputLevels as $level) {
            try {
                switch ($level) {
                    case RoutePermission::DIGEST:
                        $this->pushToQueue($this->listFactory->digest($userId), $instance);
                        $processed = true;
                        break;

                    case RoutePermission::INSTANT:
                        $this->pushToQueue($this->listFactory->instant($userId), $instance, delayable: true);
                        $processed = true;
                        break;

                    case RoutePermission::PUSH:
                        $this->pushToQueue($this->listFactory->mobile($userId), $instance, delayable: true);
                        $processed = true;
                        break;

                    case RoutePermission::EMAIL:
                        $this->pushToQueue($this->listFactory->email($userId), $instance);
                        $processed = true;
                        break;

                    default:
                        $this->log->warning("Unable to SEND: could not map priority '$level'");
                        break;
                }
            } catch (RfcComplianceException $e) {
                $this->log->warning($e->getMessage());
            } catch (PDOException $e) {
                // this needs to be escalated
                throw $e;
            } catch (Exception $e) {
                $this->log->error(get_class($e) . ' occurred during rerouting: ' . $e->getMessage(), ['exception' => $e]);
            }
        }
        return $processed;
    }


    /**
     * Iterates the installed filters, any of which will throw an exception if the message fails its validation
     *
     * @param Instance $message
     * @param User     $user
     * @return bool
     * @throws FilterException
     */
    public function validate(Instance $message, User $user)
    {
        // exceptions get thrown if it fails a given filter
        $this->filter->validate($message, $user);
        return true;
    }


    /**
     * Process retract requests
     *
     * @param Instance $instance
     * @return bool
     */
    public function processRetract(Instance $instance): bool
    {
        if ($instance->isActionRetractSingle()) {
            /** @var MessageList\Retractor $retractor */
            $retractor = $this->listFactory->retract();
            $this->log->debug("[QueueSorter] retracting single " . $retractor->getRedisMsgKey($instance));
            $retractor->push($instance);
        } elseif ($instance->isActionRetractAll()) {
            if (!empty($instance->getEventObjectId())) {
                /** @var MessageList\EventObjectList $retractor */
                $retractor = $this->listFactory->eventObjectList($instance->getEventObjectId());
                $this->log->debug("[QueueSorter] retracting event object " . $instance->getEventObjectId());
                $retractor->retract();
            } else {
                /** @var MessageList\Retractor $retractor */
                $retractor = $this->listFactory->retractLike();
                $this->log->debug("[QueueSorter] retracting all like " . $retractor->getRedisMsgKey($instance));
                $retractor->push($instance);
            }
        }
        return true;
    }


    /**
     * Adds an instance to a related event object list
     *
     * @param Instance $instance
     * @return bool
     */
    public function processEventObjectList(Instance $instance): bool
    {
        $this->log->debug('[QUEUE SORTER] Adding instance to event object list: ' . $instance->getEventObjectId());
        $this->listFactory->eventObjectList($instance->getEventObjectId())->push($instance);
        return true;
    }


    /**
     * Calculate a set of route levels relevant to this user x instance.
     *
     * @param User     $user
     * @param Instance $instance
     * @return array One or more of these constants:
     *              `* RoutePermission::MUTE`
     *              `* RoutePermission::DIGEST`
     *              `* RoutePermission::INSTANT`
     *              `* RoutePermission::PUSH`
     *              `* RoutePermission::EMAIL`
     */
    private function getReroutedLevel(User $user, Instance $instance): array
    {
        // For now just process the first topic.
        // Could foreach over the list of topics if we wanted to?
        $topic = $instance->getTopics()[0];
        try {
            $intent = $this->permMapper->findRouteLevels(
                $topic,
                $user->getRole()->getId(),
                $user->getId(),
                $instance->getFolderId()
            );
        } catch (\Schoolbox\Mapper\Exception $e) {
            $intent = [];
        }

        $delivery = $instance->retrieveDeliveryMethod();

        // Is this sent as an alert?
        // Unmute, and route to instant+push+email if not already
        if ($delivery === Instance::DELIVERY_ALERT) {
            $this->log->debug('Delivery type is alert: upgrading to instant+push+email');
            if ($this->permMapper->isMuted($intent)) {
                $intent = [];
            }
            if (!$this->permMapper->isInstant($intent)) {
                $intent[] = RoutePermission::INSTANT;
            }
            if (!$this->permMapper->isPush($intent)) {
                $intent[] = RoutePermission::PUSH;
            }
            if (!$this->permMapper->isEmail($intent)) {
                $intent[] = RoutePermission::EMAIL;
            }
        }

        // Is this sent via instant delivery?
        // Unmute, and route to instant if not already
        if ($delivery === Instance::DELIVERY_INSTANT && !$this->permMapper->isInstant($intent)) {
            $this->log->debug('Delivery type is instant: upgrading to instant');
            if ($this->permMapper->isMuted($intent)) {
                $intent = [];
            }
            $intent[] = RoutePermission::INSTANT;
        }

        if (count($intent) === 0) {
            // if we got nothing back, this user isn't meant to see this message
            $this->log->debug("Got no levels back, msg source is disabled for this user's role");
            return [RoutePermission::MUTE];
        }

        // if the user in question has no digest schedule and digest level is selected, upgrade to instant
        if (
            $this->permMapper->isDigest($intent)
            && (string)$user->getRole()->getDigestSchedule() === Role::DIGEST_NEVER
        ) {
            $this->log->info("User doesn't have digest schedule: ensuring delivery is at least instant");
            $newIntent = [RoutePermission::INSTANT];
            if ($this->permMapper->isPush($intent)) {
                $newIntent[] = RoutePermission::PUSH;
            }
            if ($this->permMapper->isEmail($intent)) {
                $newIntent[] = RoutePermission::EMAIL;
            }
            $intent = $newIntent;
        }

        return $intent;
    }

    /**
     * Push to a queue, with retry on rate-limit.
     *
     * @param QueueInterface $queue      The queue to push to.
     * @param Instance       $instance   The message instance to push.
     * @param int            $attemptNum What attempt number is this? Starts at 0, increments until jitter algorithm
     *                                   rejects it.
     * @param bool           $delayable  If true, this delivery may be delayed until after the configured quiet time has
     *                                   passed.
     *                                   Alert delivery instances may not be delayed: otherwise this will be true for
     *                                   instant delivery types (so that users aren't notified within the quiet time).
     *
     * @return void
     */
    private function pushToQueue(
        QueueInterface $queue,
        Instance $instance,
        int $attemptNum = 0,
        bool $delayable = false,
    ): void {
        if ($delayable) {
            if ($instance->retrieveDeliveryMethod() === Instance::DELIVERY_ALERT) {
                $this->log->notice('Delivery type is alert: not attempting to delay');
            } else {
                // Is the current time within the quiet period?
                $quietStart  = '15:15:00';
                $quietEnd    = '15:42:00';
                $now         = \Alaress_Date_Format::now();
                $currentTime = $now->getUsingFormat('H:i:s');

                if ($quietStart <= $quietEnd) {
                    // Start time < end time:
                    // the quiet period does not overlap 00:00
                    $quiet = ($currentTime >= $quietStart && $currentTime <= $quietEnd);
                } else {
                    // Start time > end time:
                    // the quiet period overlaps 00:00
                    $quiet = ($currentTime >= $quietStart || $currentTime <= $quietEnd);
                }
                if ($quiet) {
                    // How many seconds to go until quiet time expires?
                    $delay = strtotime($quietEnd) - strtotime($currentTime) + random_int(1, 60);
                    $this->log->notice(sprintf('Delivery is delayed %ss until at least %s (it is now %s)', $delay, $quietEnd, $currentTime));
                    $this->loop->addTimer(
                        $delay,
                        fn () => $this->pushToQueue($queue, $instance, $attemptNum, $delayable),
                    );
                    return;
                } else {
                    $this->log->notice('Delivery is not delayed');
                }
            }
        }


        try {
            $queue->push($instance);
        } catch (RateLimitException) {
            // Too many attempts already: log and give up
            if ($this->jitter->tooManyAttempts($attemptNum + 1)) {
                $this->log->warning(
                    sprintf(
                        'Queue could not be pushed to after %d attempts: message has not been delivered',
                        $attemptNum,
                    )
                );
                return;
            }

            // Otherwise add it to the timer
            $wait = $this->jitter->calculateJitter($attemptNum);
            $this->log->notice(
                sprintf('Queue is likely rate-limited: waiting %d ms before trying again', $wait)
            );
            $this->loop->addTimer(
                $wait / 1000,
                fn () => $this->pushToQueue($queue, $instance, $attemptNum + 1, $delayable),
            );
        }
    }
}
