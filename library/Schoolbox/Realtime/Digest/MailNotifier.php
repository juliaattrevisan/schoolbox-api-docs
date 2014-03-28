<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class MailNotifier {

	/**
	 * This method is executed by a worker using the 'digest' command
	 *
	 * @param Role $role - the role to digest
	 * @param null $user - ignored
	 * @param null $diff - ignored
	 * @return int
	 */
	public function processDigest($role, $user, $diff) {

		// setup
		$conn = new Connector();

		// get a list of users
		$users = UserMapper::getInstance()->findByColumn('group_id', $role->getId());

		// get their IDs
		$userIds = $users->getColumnValues('id');

		$result = 0;

		// iterate and digest
		foreach($userIds as $id) {
			$q = UserQueue::getUserDigestQueueName($id);
			$acks = array();

			// collate list of messages
			while($m = $conn->readFromQueue($q)) {
				$acks[] = $m;
			}

			// pass into mailer
			$sent = false;

			// if email sent successfully, ACK, otherwise NACK
			if($sent) {
				$conn->acceptMessages($acks);
				++$result;
			}
			else {
				$conn->rejectMessages($acks);
			}
		}

		// return the number of emails successfully sent
		return $result;
	}

} 