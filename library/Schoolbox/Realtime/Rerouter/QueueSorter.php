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
use Schoolbox\Notif\IQueueFactory;
use Schoolbox\Notif\NotifInterface;
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

	/** @var NotifInterface */
	protected $notif;

    /** @var IQueueFactory */
    protected $qFactory;

    /** @var array */
    protected $map = [
        'DI' => Logger::INFO,
        'IN' => Logger::NOTICE,
        'MO' => Logger::WARNING,
        'EM' => Logger::ERROR
    ];

    /**
     * @param FilterInterface[]     $filters
     * @param LoggerInterface       $log
     * @param NotifInterface        $notif
     * @param IQueueFactory         $qFactory
     * @param RoutePermissionMapper $permMapper
     * @throws \InvalidArgumentException
     */
    public function __construct(
        array $filters,
        LoggerInterface $log,
        NotifInterface $notif,
        IQueueFactory $qFactory,
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
			$outputLevels = $this->getReroutedLevel($user, $formatted['channel']);

            if (in_array('MU', $outputLevels)) {
                throw new FilterException("Reroute level is zero");

            } elseif (in_array('DI', $outputLevels)) {
                $method = UserQueue::Q_DIGEST;

            } elseif (in_array('IN', $outputLevels)) {
                $method = UserQueue::Q_LISTED;

            } else {
                throw new FilterException("Reroute level is unacceptable ($outputLevels)");
            }

            // put the instance in redis: it will always end up being stored in one queue or the other
            $q = $this->qFactory->setKind($method)->buildForUser($user->getId());

            if ($q instanceof RedisQueue) {
                $q->push($instance);
            }

            // cast 'listed' to 'instant'
			$newTopic = ($method == UserQueue::Q_LISTED ? 'instant' : $method) . ".user.{$user->getId()}";

            $this->log->info(sprintf('SEND %s:%s -> %s @ %s', $instance->getBaseHash(), $instance->getParamHash(), $newTopic, json_encode($outputLevels)));

            // log for each expected output level
            foreach ($outputLevels as $level) {
                if (isset($this->map[$level])) {
                    $this->notif->logTo($newTopic, $this->map[$level], $formatted['message'], $formatted['context']);
                } else {
                    $this->log->warning("Unable to SEND: could not map priority '$level'");
                }
            }

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
	 * @return array
	 */
	protected function getReroutedLevel(User $user, $channel) {
		$intent = $this->permMapper->findRouteLevels($channel, $user->getRole()->getId(), $user->getId());

		if (count($intent) == 0) {
			// if we got nothing back, this user isn't meant to see this message
            $this->log->debug("Got no levels back, msg source is disabled for this user's role");
            return [ 'MU' ];
		}

		// if the user in question has no digest schedule and digest level is selected, escalate to instant
		if ((string)$user->getRole()->getDigestSchedule() == (string)Role::DIGEST_NEVER && in_array('DI', $intent)) {
			$this->log->info("User doesn't have digest schedule: upgrading to instant");
			$intent = [ 'IN' ];
		}

		return $intent;
	}

} 