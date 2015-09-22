<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use Alaress\Redis;
use Schoolbox\Collection;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\Notif\UserQueue\UserQueueFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\WorkNotifier;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class MailNotifier extends WorkNotifier {

    /** @var UserQueueFactory */
    protected $uqf;


    /**
     * @return UserQueueFactory
     */
    public function getUserQueueFactory()
    {
        // lazyload
        if (empty($this->uqf)) {
            $this->uqf = new UserQueueFactory();
            $this->uqf
                 ->setRedis(Redis::getClientInstance())
                 ->setKind(UserQueueFactory::UQ_DIGEST);
        }
        return $this->uqf;
    }


	/**
	 * This method is executed by a worker using the 'digest' command
	 *
	 * @param Role $role - the role to digest
	 * @return int
	 */
	public function process($role) {

		// get a list of users
        /** @var Collection|User[] $users */
		$users = UserMapper::getInstance()->findByColumn('group_id', $role->getId());

		$this->log()->notice("Preparing to digest {$role->getName()}: {$users->count()} users");

        $result = 0;
		$dir    = $this->conf->get('digest_oldest_first') ? SORT_DESC : SORT_ASC;
        $core   = new CoreContext($this->conf);

		// iterate and digest
		foreach($users as $user) {

            $decorator = new Decorator();
            $email     = new TwigEmail('digestmail.twig', $this->conf->get('digest_subject'));
            $uq        = $this->getUserQueueFactory()->buildForUser($user->getId());

            $email->setCoreContext($core->getContext());

            $uq->fetchNotificationsIntoBundle($decorator, true);

			if(!$decorator->count()) {
				// this user has nothing to digest
				$this->log()->debug("{$user->getUsername()} has nothing to digest");
                unset($uq, $decorator, $email, $user);
				continue;
			}

            $this->log()->debug(sprintf("Digesting %d item%s for user %s", $decorator->count(), $decorator->count() == 1 ? '' : 's', $user->getUsername()));

			$context = $decorator->renderAsTwigContext($core->getContext(), 0, $dir);

            try {
                // explicitly an rfc822 email spec, so it sets, not adds
                $address = sprintf("%s <%s>", $user->formatName(), $user->getEmail());
                $email->setContext($context)->setTo($address);

                // away she goes
                $sent = $email->send();

                // if email sent successfully, ACK, otherwise NACK
                if($sent) {
                    $this->log()->notice("Mail sent successfully to $address, dropping messages from queue");
                    $uq->drain();
                    ++$result;
                }
                else {
                    $this->log()->warning("Failed to email to $address");
                }

                unset($uq, $decorator, $email, $user);

            } catch (\Swift_SwiftException $e) {
                $this->log()->warning(get_class($e) . ': ' . $e->getMessage(), array($e));
            } catch (\Twig_Error_Runtime $e) {
                $this->log()->error(get_class($e) . ': ' . $e->getMessage(), array($e));
                if (strpos($e->getMessage(), 'Invalid multibyte sequence in argument') !== false) {
                    // this means the template couldn't render the mail because of bad data, so we have to flush it :/
                    $uq->drain();
                    $this->log()->warning('Message content cannot be rendered; data lost');
                }
            }

		}

        unset($users);

		// return the number of emails successfully sent
		$this->log()->info("Finished digesting {$role->getName()}: $result emails sent");
		return $result;
	}

}
