<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use Alaress\Async\Notification\Instance\Instance;
use Alaress\Cli\Debug;
use InvalidArgumentException;
use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\Notif\Message\External\TargetedConverter;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Serializable;

class MailNotifier implements Serializable {

    /** @var IConfigLoader */
    protected $conf;

	/** @var Debug */
	protected $debug;

	/**
	 * @param IConfigLoader $configLoader
	 * @param Debug         $debug
	 */
    public function __construct($configLoader = null, $debug = null) {
        $this->conf = $configLoader ?: ConfigMapper::getInstance();
	    $this->setDebug($debug ?: new Debug(null));
    }


	/**
	 * @param Debug $debug
	 */
	public function setDebug($debug) {
		$this->debug = $debug;
	}


	/**
	 * This method is executed by a worker using the 'digest' command
	 *
	 * @param Role $role - the role to digest
	 * @return int
	 */
	public function processDigest($role) {

		// setup
		$conn = Connector::getInstance();

		// get a list of users
        /** @var Collection|User[] $users */
		$users = UserMapper::getInstance()->findByColumn('group_id', $role->getId());

		$this->debug->v("Preparing to digest {$role->getName()}: {$users->count()} users");

		$result = 0;

		$decorator = new Decorator();
		$dir = $this->conf->get('digest_oldest_first') ? SORT_DESC : SORT_ASC;

		// iterate and digest
		foreach($users as $user) {
			$email = new TwigEmail('digestmail.twig', $this->conf->get('digest_subject'));
			$this->debug->v("Digesting {$user->getUsername()}");
			$q = UserQueue::getUserDigestQueueName($user->getId());
			$acks = array();

			// collate list of messages
			while($m = $conn->readFromQueue($q)) {
				$acks[] = $m;
				try {
					if($formatted = json_decode($m->body, true)) {
						try {
							$inst = Instance::jsonUnserialize($formatted['message']);
						}
						catch(InvalidArgumentException $e) {
							$conv = new TargetedConverter();
							$inst = $conv->convert($formatted['message']);
						}
						$decorator->appendInstance($inst);
					}
				}
				catch(InvalidArgumentException $e) {
					// meh. just ignore this message then
					$this->debug->v("Unparseable message instance detected", '*');
				}
			}

			if(!count($acks)) {
				// this user has nothing to digest
				$this->debug->v("{$user->getUsername()} has nothing to digest");
				continue;
			}

			$context = $decorator->renderAsTwigContext($email->getCoreContext(), $dir);
			$this->debug->v(sprintf('Collated %s instances into %s notifications to digest', count($acks), count($decorator->getAll())));

			// explicitly an rfc822 email spec, so it sets, not adds
			$email->setContext($context)->setTo(sprintf("%s <%s>", $user->formatName(), $user->getEmail()));

			// away she goes
			$sent = $email->send();

            // if email sent successfully, ACK, otherwise NACK
			if($sent) {
				$this->debug->v("Mail sent successfully");
				$conn->acceptMessages($acks);
				++$result;
			}
			else {
				$this->debug->v("Failed to email, rejecting messages", '*');
				$conn->rejectMessages($acks);
			}
		}

		// return the number of emails successfully sent
		$this->debug->v("Finished digesting {$role->getName()}: $result emails sent");
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
