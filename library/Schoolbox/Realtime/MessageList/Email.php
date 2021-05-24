<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Async\Notification\NotificationBundle;
use Alaress\Async\Queue\QueueInterface;
use Schoolbox\Realtime\Mailer;
use Schoolbox\User;
use Swift_RfcComplianceException;
use UnderflowException;

class Email implements QueueInterface
{

    /** @var User */
    private $user;

    /** @var Mailer */
    private $mailer;

    /**
     * This is declared within push()
     *
     * @var Instance|null
     */
    private $messageInstance;


    /**
     * @param User   $user
     * @param Mailer $mailer
     */
    public function __construct(User $user, Mailer $mailer)
    {
        $this->user   = $user;
        $this->mailer = $mailer;
    }


    /**
     * Returns the name of this queue
     *
     * @return string
     */
    public function getName()
    {
        return "{$this->user->getId()}:email";
    }


    /**
     * Returns whether or not this queue contains the given instance
     *
     * @param Instance $instance
     * @return bool
     */
    public function contains(Instance $instance)
    {
        return $this->messageInstance === $instance;
    }


    /**
     * Request to drain the queue of its contents.
     *
     * @return void
     */
    public function drain()
    {
        $this->messageInstance = null;
    }


    /**
     * Push the given instance onto this queue
     *
     * @param Instance $instance
     * @throws Swift_RfcComplianceException
     * @throws UnderflowException
     * @return void
     */
    public function push(Instance $instance)
    {
        $this->messageInstance = $instance;

        // this feels inside out, but it lets us have a common message delivery
        // interface while also having a common email delivery implementation
        $this->mailer->sendListContents($this, $this->user, 'instantmail.twig');
    }


    /**
     * Populates the given Bundle with all the notifications this queue contains
     *
     * @param NotificationBundle $bundle    the bundle to populate
     * @param bool               $dropCache whether or not any internally-held cache should be dropped
     * @return void
     */
    public function fetchIntoBundle(NotificationBundle $bundle, $dropCache = false)
    {
        if ($this->messageInstance) {
            $bundle->appendInstance($this->messageInstance);
        }
    }
}
