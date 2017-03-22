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
use Exception;
use InvalidArgumentException;
use OutOfBoundsException;
use PDOException;
use Psr\Log\LoggerInterface;
use React\Stomp\Protocol\Frame;
use Schoolbox\Notif\NotifInterface;
use Schoolbox\Realtime\Message\RoutePermission;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\Rerouter\Filter\FilterException;
use Schoolbox\Realtime\Rerouter\Filter\FilterInterface;
use Schoolbox\Role;
use Schoolbox\User;
use Swift_RfcComplianceException;

class QueueSorter implements FilterInterface
{
    const TOPIC_DIGEST  = 'digest';
    const TOPIC_INSTANT = 'instant';


	/** @var FilterInterface */
	private $filter;

	/** @var LoggerInterface */
	private $log;

	/** @var RoutePermissionMapper */
	private $permMapper;

	/** @var NotifInterface */
	private $notif;


    /**
     * @param FilterInterface       $filter
     * @param LoggerInterface       $log
     * @param NotifInterface        $notif
     * @param RoutePermissionMapper $permMapper
     */
    public function __construct(
        FilterInterface $filter,
        LoggerInterface $log,
        NotifInterface $notif,
        RoutePermissionMapper $permMapper
    ) {
        $this->filter     = $filter;
        $this->log        = $log;
        $this->permMapper = $permMapper;
        $this->notif      = $notif;
    }


    /**
	 * @param User $user
	 * @param Frame $frame
	 * @throws InvalidArgumentException
     * @throws PDOException
	 * @return bool
	 */
	public function process(User $user, Frame $frame)
    {
		$formatted = json_decode($frame->body, true);
		$instance  = Instance::jsonUnserialize($formatted['message'], $formatted['channel']);

		try {
			$this->validate($instance, $formatted, $user);

			$outputLevels = $this->getReroutedLevel($user, $formatted['channel']);
			$method       = $this->getMethod($outputLevels);
            $newTopic     = "{$method}.user.{$user->getId()}";

            $this->log->info(sprintf('SEND %s:%s -> %s @ %s', $instance->getBaseHash(), $instance->getParamHash(), $newTopic, json_encode($outputLevels)));

            // log for each expected output level
            foreach ($outputLevels as $level) {
                try {
                    $this->notif->logTo($newTopic, $this->permMapper->getSeverity($level), $formatted['message'], $formatted['context']);

                } catch (OutOfBoundsException $e) {
                    $this->log->warning("Unable to SEND: could not map priority '$level'");
                }
            }

			return true;

		} catch (FilterException $e) {
			$this->log->notice(sprintf('DROP %s:%s -> %s: %s', $instance->getBaseHash(), $instance->getParamHash(), $user->getId(), $e->getMessage()));
			return true;

		} catch (Swift_RfcComplianceException $e) {
            $this->log->warning($e->getMessage());

        } catch (PDOException $e) {
            // this needs to be escalated
            throw $e;

        } catch (Exception $e) {
			$this->log->error(get_class($e) . ' occurred during rerouting: ' . $e->getMessage(), array($e));
		}

		return false;
	}


    /**
     * @param string[] $outputLevels
     * @return string
     * @throws FilterException
     */
    private function getMethod(array $outputLevels)
    {
        if ($this->permMapper->isMuted($outputLevels)) {
            throw new FilterException('Reroute level is zero');
        }

        if ($this->permMapper->isDigest($outputLevels)) {
            return static::TOPIC_DIGEST;
        }

        if ($this->permMapper->isInstant($outputLevels)) {
            return static::TOPIC_INSTANT;
        }

        throw new FilterException('Reroute level is unacceptable: ' . json_encode($outputLevels));
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
	public function validate($message, $formatted, $user)
    {
        // exceptions get thrown if it fails a given filter
        $this->filter->validate($message, $formatted, $user);
	}


	/**
	 * @param User $user
	 * @param string $channel
	 * @return array
	 */
	private function getReroutedLevel(User $user, $channel)
    {
		$intent = $this->permMapper->findRouteLevels($channel, $user->getRole()->getId(), $user->getId());

		if (count($intent) === 0) {
			// if we got nothing back, this user isn't meant to see this message
            $this->log->debug("Got no levels back, msg source is disabled for this user's role");
            return [ RoutePermission::MUTE ];
		}

		// if the user in question has no digest schedule and digest level is selected, escalate to instant
		if ($this->permMapper->isDigest($intent) && (string)$user->getRole()->getDigestSchedule() === (string)Role::DIGEST_NEVER) {
			$this->log->info("User doesn't have digest schedule: upgrading to instant");
			$intent = [ RoutePermission::INSTANT ];
		}

		return $intent;
	}

} 
