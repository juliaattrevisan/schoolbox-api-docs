<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter;


use Alaress\Async\Stomp\SubscriptionList;
use OutOfBoundsException;
use Schoolbox\Collection;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Notif\Queue\UserQueueFactory;
use Schoolbox\User;
use Schoolbox\UserMapper;

class UserSubscriptionList extends SubscriptionList {

    /** @var UserQueueFactory */
    protected $uqf;


    /**
     * @param UserQueueFactory $uqf
     */
    public function __construct(UserQueueFactory $uqf)
    {
        $this->uqf = $uqf;
    }


	/**
	 * @param UserMapper $source
	 * @param bool       $clear
	 * @return void
	 */
	public function importFromSource($source, $clear = true) {
		if($clear) {
			$this->queues = array();
		}

		/** @var Collection|User[] $users */
		$users = $source->findAll();

		foreach($users as $user) {
            try {
                // construct the queue, but don't prepopulate it
                $name = $this->uqf->buildUserQueue($user, 0)->findQueue(UserQueue::Q_TOSORT);
                $this->queues[$name] = $source->findById($user->getId()); // TODO srsly. this has to change.
            } catch (OutOfBoundsException $e) {
                // noop: no sorting queue found for this user
            }
		}
	}

}