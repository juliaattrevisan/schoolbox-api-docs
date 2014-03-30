<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use Schoolbox\Collection;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\Notif\MQ\Connector;
use Schoolbox\Notif\Queue\UserQueue;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class MailNotifier {

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
			$acks = array();

			// collate list of messages
			while($m = $conn->readFromQueue($q)) {
				$acks[] = $m;
			}

            $email->setTo(array('name' => $user->formatName(), 'email' => $user->getEmail()));
            $email->setContext($this->convertMessagesToContext($acks));

			// away she goes
			$sent = $email->send();

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


    protected function convertMessagesToContext($messages) {
        // TODO rearrange data to be more twig-friendly
        return $messages;
    }

} 