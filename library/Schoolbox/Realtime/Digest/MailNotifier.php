<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Digest;


use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmail;
use Schoolbox\Realtime\MessageList\IFactory;
use Schoolbox\Realtime\Notification\Decorator;
use Schoolbox\Realtime\WorkNotifier;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

class MailNotifier extends WorkNotifier
{
    /** @var IFactory */
    private $factory;


    /**
     * @param IFactory        $factory
     * @param IConfigLoader   $configLoader
     * @param LoggerInterface $log
     */
    public function __construct(IFactory $factory, IConfigLoader $configLoader = null, LoggerInterface $log = null)
    {
        parent::__construct($configLoader, $log);
        $this->factory = $factory;
    }


    /**
	 * This method is executed by a worker using the 'digest' command
	 *
	 * @param Role $role - the role to digest
	 * @return int
	 */
	public function process($role)
    {
		// get a list of users
        /** @var Collection|User[] $users */
		$users = UserMapper::getInstance()->findByColumn('group_id', $role->getId());

		$this->log()->notice("Preparing to digest {$role->getName()}: {$users->count()} users");

        $result = 0;
		$dir    = $this->conf->get('digest_oldest_first') ? SORT_DESC : SORT_ASC;
        $core   = new CoreContext($this->conf);

		// iterate and digest
		foreach ($users as $user) {

            $decorator  = new Decorator();
            $email      = new TwigEmail('digestmail.twig', $this->conf->get('digest_subject'));
            $digestList = $this->factory->digest($user->getId());

            // apply core context to the email
            $email->setCoreContext($core->getContext());

            // drop all the digest messages into the decorator
            $digestList->fetchIntoBundle($decorator, true);

			if (!$decorator->count()) {
				// this user has nothing to digest
				$this->log()->debug("{$user->getUsername()} has nothing to digest");
                unset($digestList, $decorator, $email, $user);
				continue;
			}

            $this->log()->debug(sprintf('Digesting %d item%s for user %s', $decorator->count(), $decorator->count() === 1 ? '' : 's', $user->getUsername()));

            $emailAddress = trim($user->getEmail());

            if (empty($emailAddress)) {
                $this->log()->warning("{$user->getUsername()} has no email address");
                unset($digestList, $decorator, $email, $user);
                continue;
            }

			$context = $decorator->renderAsTwigContext($core->getContext(), 0, $dir);

            try {
                // explicitly an rfc822 email spec, so it sets, not adds
                $address = sprintf('%s <%s>', $user->formatName(), $emailAddress);
                $email->setContext($context)->setTo($address);

                // away she goes
                $sent = $email->send();

                // if email sent successfully, ACK, otherwise NACK
                if($sent) {
                    $this->log()->notice("Mail sent successfully to $address, dropping messages from queue");
                    $digestList->drain();
                    ++$result;
                }
                else {
                    $this->log()->warning("Failed to email to $address");
                }

                unset($digestList, $decorator, $email, $user);

            } catch (\Swift_SwiftException $e) {
                $this->log()->warning(get_class($e) . ': ' . $e->getMessage(), array($e));
            } catch (\Twig_Error_Runtime $e) {
                $this->log()->error(get_class($e) . ': ' . $e->getMessage(), array($e));
                if (strpos($e->getMessage(), 'Invalid multibyte sequence in argument') !== false) {
                    // this means the template couldn't render the mail because of bad data, so we have to flush it :/
                    $digestList->drain();
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
