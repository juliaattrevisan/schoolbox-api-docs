<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing;


use Alaress\Async\Notification\Instance\Instance;
use Exception;
use InvalidArgumentException;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\MessageList;
use Schoolbox\Realtime\Routing\Filter\FilterException;
use Schoolbox\Realtime\Routing\Filter\FilterInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Swift_RfcComplianceException;

class QueueSorter implements FilterInterface
{
    /** @var FilterInterface */
    private $filter;

    /** @var LoggerInterface */
    private $log;

    /** @var RoutePermissionMapper */
    private $permMapper;

    /** @var MessageList\IListFactory */
    private $listFactory;


    /**
     * @param FilterInterface                 $filter
     * @param LoggerInterface                 $log
     * @param MessageList\IPublicationFactory $listFactory
     * @param RoutePermissionMapper           $permMapper
     */
    public function __construct(
        FilterInterface $filter,
        LoggerInterface $log,
        MessageList\IPublicationFactory $listFactory,
        RoutePermissionMapper $permMapper
    ) {
        $this->filter      = $filter;
        $this->log         = $log;
        $this->listFactory = $listFactory;
        $this->permMapper  = $permMapper;
    }


    /**
     * @param User     $user
     * @param Instance $instance
     * @return bool
     * @throws PDOException
     * @throws InvalidArgumentException
     */
    public function process(User $user, Instance $instance)
    {
        $userId = $user->getId();

        try {
            $this->validate($instance, $user);

            $outputLevels = $this->getReroutedLevel($user, $instance);

            if ($this->permMapper->isMuted($outputLevels)) {
                throw new FilterException('Reroute level is zero');
            }

            $this->log->info(
                sprintf(
                    'SEND %s:%s -> user %d @ %s',
                    $instance->getBaseHash(),
                    $instance->getParamHash(),
                    $userId,
                    json_encode($outputLevels)
                )
            );

            // push for each output level
            foreach ($outputLevels as $level) {
                switch ($level) {
                    case RoutePermission::DIGEST:
                        $this->listFactory->digest($userId)->push($instance);
                        break;

                    case RoutePermission::INSTANT:
                        $this->listFactory->instant($userId)->push($instance);
                        break;

                    case RoutePermission::PUSH:
                        $this->listFactory->mobile($userId)->push($instance);
                        break;

                    case RoutePermission::EMAIL:
                        $this->listFactory->email($userId)->push($instance);
                        break;

                    default:
                        $this->log->warning("Unable to SEND: could not map priority '$level'");
                        break;
                }
            }
            return true;

        } catch (FilterException $e) {
            $this->log->notice(
                sprintf(
                    'DROP %s:%s -> user %d: %s',
                    $instance->getBaseHash(),
                    $instance->getParamHash(),
                    $userId,
                    $e->getMessage()
                )
            );
            return true;

        } catch (Swift_RfcComplianceException $e) {
            $this->log->warning($e->getMessage());

        } catch (PDOException $e) {
            // this needs to be escalated
            throw $e;

        } catch (Exception $e) {
            $this->log->error(get_class($e) . ' occurred during rerouting: ' . $e->getMessage(), ['exception' => $e]);
        }

        return false;
    }


    /**
     * Iterates the installed filters, any of which will throw an exception if the message fails its validation
     *
     * @param Instance $message
     * @param User     $user
     * @return void
     * @throws FilterException
     */
    public function validate(Instance $message, User $user)
    {
        // exceptions get thrown if it fails a given filter
        $this->filter->validate($message, $user);
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
        $topic  = $instance->getTopics()[0];
        $intent = $this->permMapper->findRouteLevels(
            $topic,
            $user->getRole()->getId(),
            $user->getId(),
            $instance->getFolderId()
        );

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
        if ($this->permMapper->isDigest($intent)
            && (string)$user->getRole()->getDigestSchedule() === Role::DIGEST_NEVER
        ) {
            $this->log->info("User doesn't have digest schedule: upgrading to at least instant");
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

}
