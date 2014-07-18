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
use Alaress\Cli\Debug;
use Exception;
use InvalidArgumentException;
use Monolog\Logger;
use React\Stomp\Protocol\Frame;
use Schoolbox\Notif\Message\External\TargetedConverter;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Rerouter\Filter\FilterException;
use Schoolbox\Realtime\Rerouter\Filter\FilterInterface;
use Schoolbox\Role;
use Schoolbox\User;

class QueueSorter implements FilterInterface {

	/** @var FilterInterface[] */
	protected $filters;

	/** @var Debug */
	protected $debug;

	/** @var RoutePermissionMapper */
	protected $permMapper;

	/** @var Notif */
	protected $notif;


	/**
	 * @param FilterInterface[]      $filters
	 * @param Debug                  $debug
	 * @param Notif                  $notif
	 * @param RoutePermissionMapper  $permMapper
	 * @throws \InvalidArgumentException
	 */
	public function __construct($filters, $debug, $notif, $permMapper) {
		$this->filters = array();

		foreach($filters as $filter) {
			if($filter instanceof FilterInterface) {
				$this->filters[] = $filter;
			}
		}

		if(!count($this->filters)) {
			throw new InvalidArgumentException("At least one valid filter must be specified");
		}

		$this->debug      = $debug;
		$this->permMapper = $permMapper;
		$this->notif      = $notif;
	}


	/**
	 * @param User $user
	 * @param Frame $frame
	 * @throws InvalidArgumentException
	 * @return bool
	 */
	public function process($user, $frame) {
		// anything echoed in this method is caught in an output buffer and piped to the log
		$formatted = json_decode($frame->body, true);

		try {
			$instance = Instance::jsonUnserialize($formatted['message']);
		}
		catch(InvalidArgumentException $e) {

			// TODO once TargetedMessage has been fully deprecated, this catch can be removed
			$conv = new TargetedConverter();
			$instance = $conv->convert($formatted['message']);
		}

		try {
			$this->validate($instance, $formatted, $user);
			$newLevel = $this->getReroutedLevel($user, $formatted['channel']);

			switch($newLevel) {
				// digest
				case Logger::INFO:
					$method = 'digest';
					break;

				// instant
				case Logger::NOTICE:
				// instant + email
				case Logger::WARNING:
				case Logger::ERROR:
					$method = 'instant';
					break;

				// total suppression
				case 0:
					throw new FilterException("Reroute level is zero");

				//invalid level
				default:
					throw new FilterException("Reroute level is unacceptable ($newLevel)");
			}

			$newTopic = "$method.user.{$user->getId()}";

			$this->debug->v("Rerouting message to '$newTopic' at level $newLevel");
			$this->notif->logTo($newTopic, $newLevel, $formatted['message'], $formatted['context']);

			return true;
		}
		catch(FilterException $e) {
			$this->debug->v("Message routed to /dev/null: ".$e->getMessage());
			return true;
		}
		catch(Exception $e) {
			$this->debug->v("Exception occurred during rerouting: $e", '*');
		}

		return false;
	}


	/**
	 * Returns true if this message is considered valid, or false if it should be dismissed
	 *
	 * @param Instance $message
	 * @param array    $formatted
	 * @param User     $user
	 * @throws FilterException
	 * @return bool
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
	protected function getReroutedLevel($user, $channel) {
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
			$this->debug->v("User doesn't have digest schedule: upgrading to instant");
			$intent = Logger::NOTICE;
		}

		return $intent;
	}

} 