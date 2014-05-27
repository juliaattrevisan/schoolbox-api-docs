<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use PhpAmqpLib\Message\AMQPMessage;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\Notif\Message\External\TargetedMessage;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Serializable;

class MailNotifier implements Serializable {

    /** @var IConfigLoader */
    protected $conf;

    /**
     * @param IConfigLoader $configLoader
     */
    public function __construct($configLoader = null) {
        $this->conf = $configLoader ?: ConfigMapper::getInstance();
    }

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
        /** @var Collection|User[] $users */
		$users = UserMapper::getInstance()->findByColumn('group_id', $role->getId());

		$result = 0;

        $email = new TwigEmail('digestmail.twig', $this->conf->get('digest_subject'));

		// iterate and digest
		foreach($users as $user) {
			$q = UserQueue::getUserDigestQueueName($user->getId());
			$sent = false;
			$acks = array();

			// collate list of messages
			while($m = $conn->readFromQueue($q)) {
				$acks[] = $m;
			}

			if(!count($acks)) {
				// this user has nothing to digest
				continue;
			}

			$email->buildContextFromMessageList($this->amqp2targeted($acks));

			if(count($email->getContext())) {
				// explicitly an rfc822 email spec, so it sets, not adds
				$email->setTo(sprintf("%s <%s>", $user->formatName(), $user->getEmail()));
				// away she goes
				$sent = $email->send();
			}

            // if email sent successfully, ACK, otherwise NACK
			if($sent && false) {
				$conn->acceptMessages($acks);
				++$result;
			}
			else {
				$conn->rejectMessages($acks);
			}
			break;
		}

		// return the number of emails successfully sent
		return $result;
	}

	/**
	 * Convert a batch of AMQPMessages to TargetedMessages
	 *
	 * @deprecated TODO write amqp2instance() instead
	 * @param AMQPMessage[] $messages
	 * @return TargetedMessage[]
	 */
	protected function amqp2targeted($messages) {
		$result = array();

		foreach($messages as $amqp) {
			$formatted = json_decode($amqp->body, true);

			/** @var TargetedMessage $message */
			$result[] = unserialize($formatted['message']);
		}

		return $result;
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * String representation of object
	 * @link http://php.net/manual/en/serializable.serialize.php
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		// no serialization
		return 'mailnotifier';
	}

	/**
	 * (PHP 5 &gt;= 5.1.0)<br/>
	 * Constructs the object
	 * @link http://php.net/manual/en/serializable.unserialize.php
	 * @param string $serialized <p>
	 * The string representation of the object.
	 * </p>
	 * @return void
	 */
	public function unserialize($serialized) {
		// no serialization
		$this->__construct();
	}
}