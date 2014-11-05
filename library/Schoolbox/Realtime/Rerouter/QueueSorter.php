<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Queue\RedisQueue;
use Alaress\Async\UserQueue\UserQueue;
use Exception;
use InvalidArgumentException;
use Monolog\Logger;
use PDOException;
use Psr\Log\LoggerInterface;
use React\Stomp\Protocol\Frame;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Queue\QueueFactory;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Rerouter\Filter\FilterException;
use Schoolbox\Realtime\Rerouter\Filter\FilterInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Swift_RfcComplianceException;

class QueueSorter implements FilterInterface {

	/** @var FilterInterface[] */
	protected $filters;

	/** @var LoggerInterface */
	protected $log;

	/** @var RoutePermissionMapper */
	protected $permMapper;

	/** @var Notif */
	protected $notif;

    /** @var QueueFactory */
    protected $qFactory;


    /**
     * @param FilterInterface[]     $filters
     * @param LoggerInterface       $log
     * @param Notif                 $notif
     * @param QueueFactory          $qFactory
     * @param RoutePermissionMapper $permMapper
     * @throws \InvalidArgumentException
     */
    public function __construct(
        array $filters,
        LoggerInterface $log,
        Notif $notif,
        QueueFactory $qFactory,
        RoutePermissionMapper $permMapper
    ) {
        $this->filters = array();

        foreach ($filters as $filter) {
            if ($filter instanceof FilterInterface) {
                $this->filters[] = $filter;
            }
        }

        if (!count($this->filters)) {
            throw new InvalidArgumentException("At least one valid filter must be specified");
        }

        $this->log        = $log;
        $this->permMapper = $permMapper;
        $this->notif      = $notif;
        $this->qFactory   = $qFactory;
    }


    /**
	 * @param User $user
	 * @param Frame $frame
	 * @throws InvalidArgumentException
     * @throws PDOException
	 * @return bool
	 */
	public function process(User $user, Frame $frame) {
		// anything echoed in this method is caught in an output buffer and piped to the log
		$formatted = json_decode($frame->body, true);
        $instance  = Instance::jsonUnserialize($formatted['message']);

		try {
			$this->validate($instance, $formatted, $user);
			$newLevel = $this->getReroutedLevel($user, $formatted['channel']);

			switch($newLevel) {
				// digest
				case Logger::INFO:
					$method = UserQueue::Q_DIGEST;
					break;

				// instant
				case Logger::NOTICE:
				// instant + email
				case Logger::WARNING:
				case Logger::ERROR:
                    $method = UserQueue::Q_LISTED;
					break;

				// total suppression
				case 0:
					throw new FilterException("Reroute level is zero");

				//invalid level
				default:
					throw new FilterException("Reroute level is unacceptable ($newLevel)");
			}

            // put the instance in redis: it will always end up being stored in one queue or the other
            $q = $this->qFactory->setKind($method)->buildForUser($user->getId());

            if ($q instanceof RedisQueue) {
                $q->push($instance);
            }

            // cast 'listed' to 'instant'
			$newTopic = ($method == UserQueue::Q_LISTED ? 'instant' : $method) . ".user.{$user->getId()}";

			$this->log->info(sprintf('SEND %s:%s -> %s @ %s', $instance->getBaseHash(), $instance->getParamHash(), $newTopic, $newLevel));
			$this->notif->logTo($newTopic, $newLevel, $formatted['message'], $formatted['context']);



			return true;
		}
		catch(FilterException $e) {
			$this->log->notice(sprintf('DROP %s:%s -> %s: %s', $instance->getBaseHash(), $instance->getParamHash(), $user->getId(), $e->getMessage()));
			return true;
		}
        catch(Swift_RfcComplianceException $e) {
            $this->log->warning($e->getMessage());
        }
        catch(PDOException $e) {
            // this needs to be escalated
            throw $e;
        }
		catch(Exception $e) {
			$this->log->error(get_class($e) . " occurred during rerouting: " . $e->getMessage(), array($e));
		}

		return false;
	}


	/**
	 * Iterates the installed filters, any of which will throw an exception if the message fails its validation
	 *
	 * @param Instance $message
	 * @param array    $formatted
	 * @param User     $user
	 * @throws FilterException
	 * @return void
	 */
	public function validate($message, $formatted, $user) {
		foreach($this->filters as $filter) {
			// exceptions get thrown if it fails a given filter
			$filter->validate($message, $formatted, $user);
		}
	}


	/**
	 * @param User $user
	 * @param string $channel
	 * @return int
	 */
	protected function getReroutedLevel(User $user, $channel) {
		$levels = $this->permMapper->findRouteLevels($channel, $user->getRole()->getId(), $user->getId());

		if(!count($levels)) {
			// if we got nothing back, this user isn't meant to see this message
			return 0;
		}

		// ensure bounds checking
		$intent = is_null($levels['out']) ? $levels['def'] : $levels['out'];
		$intent = max($intent, $levels['min']);
		$intent = min($intent, $levels['max']);

		// if the user in question has no digest schedule and digest level is selected, escalate to instant
		if((string)$user->getRole()->getDigestSchedule() == (string)Role::DIGEST_NEVER && $intent == Logger::INFO) {
			$this->log->info("User doesn't have digest schedule: upgrading to instant");
			$intent = Logger::NOTICE;
		}

		return $intent;
	}

} 