<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Digest;

use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmailFactory;
use Schoolbox\Realtime\Mailer;
use Schoolbox\Realtime\MessageList\IListFactory;
use Schoolbox\Realtime\WorkNotifier;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use UnderflowException;

class MailNotifier extends WorkNotifier
{
    /** @var IListFactory */
    private $factory;

    /** @var Mailer */
    private $mailer;

    /** @var UserMapper */
    private $userMapper;

    /**
     * @param IListFactory    $factory
     * @param UserMapper      $userMapper
     * @param IConfigLoader   $configLoader
     * @param LoggerInterface $log
     */
    public function __construct(
        IListFactory $factory,
        UserMapper $userMapper,
        IConfigLoader $configLoader = null,
        LoggerInterface $log = null
    ) {
        parent::__construct($configLoader, $log);

        $this->factory    = $factory;
        $this->userMapper = $userMapper;

        // set up a mailer (we forcibly exclude reply-to headers from digest emails)
        $this->mailer = new Mailer(new TwigEmailFactory($this->conf, $this->log()), $this->log(), $userMapper, false);
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
        $users = $this->userMapper->findByColumn('group_id', $role->getId());

        $this->log()->notice("Preparing to digest {$role->getName()}: {$users->count()} users");

        $result = 0;
        $dir    = $this->conf->get('digest_oldest_first') ? SORT_DESC : SORT_ASC;

        // iterate and digest
        foreach ($users as $user) {
            try {
                $result += (int)$this->mailer->sendListContents(
                    $this->factory->digest($user->getId()),
                    $user,
                    'digestmail.twig',
                    $this->conf->get('digest_subject'),
                    $dir
                );
            } catch (RfcComplianceException $e) {
                $this->log()->warning($e->getMessage(), [ 'exception' => $e ]);
            } catch (UnderflowException $e) {
                $this->log()->debug("{$user->getUsername()} has nothing to digest");
            }
        }

        unset($users);

        // return the number of emails successfully sent
        $this->log()->info("Finished digesting {$role->getName()}: $result emails sent");
        return $result;
    }

    /**
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize($this->factory);
    }


    /**
     * @param string $serialized
     * @return void
     */
    public function unserialize($serialized)
    {
        $this->__construct(unserialize($serialized), UserMapper::getInstance());
    }
}
