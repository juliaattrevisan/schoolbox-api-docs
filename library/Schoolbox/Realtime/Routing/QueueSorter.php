<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
    const TOPIC_DIGEST  = 'digest';
    const TOPIC_INSTANT = 'instant';


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
	 * @throws InvalidArgumentException
     * @throws PDOException
	 * @return bool
	 */
	public function process(User $user, Instance $instance)
    {
        $userId = $user->getId();

		try {
			$this->validate($instance, $user);

			// just take the first topic
			$outputLevels = $this->getReroutedLevel($user, $instance->getTopics()[0]);

            if ($this->permMapper->isMuted($outputLevels)) {
                throw new FilterException('Reroute level is zero');
            }

            $this->log->info(sprintf('SEND %s:%s -> user %d @ %s', $instance->getBaseHash(), $instance->getParamHash(), $userId, json_encode($outputLevels)));

            // push for each output level
            foreach ($outputLevels as $level) {
                switch ($level) {
                    case RoutePermission::DIGEST:
                        $this->listFactory->digest($userId)->push($instance);
                        break;

                    case RoutePermission::INSTANT:
                        $this->listFactory->listed($userId)->push($instance);
                        $this->listFactory->unread($userId)->push($instance);
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
			$this->log->notice(sprintf('DROP %s:%s -> user %d: %s', $instance->getBaseHash(), $instance->getParamHash(), $userId, $e->getMessage()));
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
	 * Iterates the installed filters, any of which will throw an exception if the message fails its validation
	 *
	 * @param Instance $message
	 * @param User     $user
	 * @throws FilterException
	 * @return void
	 */
	public function validate(Instance $message, User $user)
    {
        // exceptions get thrown if it fails a given filter
        $this->filter->validate($message, $user);
	}


	/**
     * Calculate a set of route levels relevant to this user x topic.
     * Will be a subset of [ MU, DI, IN, MO, EM ]
     *
	 * @param User   $user
	 * @param string $topic
	 * @return string[]
	 */
	private function getReroutedLevel(User $user, $topic)
    {
		$intent = $this->permMapper->findRouteLevels($topic, $user->getRole()->getId(), $user->getId());

		if (count($intent) === 0) {
			// if we got nothing back, this user isn't meant to see this message
            $this->log->debug("Got no levels back, msg source is disabled for this user's role");
            return [ RoutePermission::MUTE ];
		}

		// if the user in question has no digest schedule and digest level is selected, upgrade to instant
		if ($this->permMapper->isDigest($intent) && (string)$user->getRole()->getDigestSchedule() == (string)Role::DIGEST_NEVER) {
			$this->log->info("User doesn't have digest schedule: upgrading to instant");
			$intent = [ RoutePermission::INSTANT ];
		}

		return $intent;
	}

} 
