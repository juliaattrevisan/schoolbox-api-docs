<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;
use Predis\ClientInterface;
use Schoolbox\MobileApp;
use Schoolbox\Realtime\Mailer;
use Schoolbox\UserMapper;

class PublicationFactory extends ListFactory implements IPublicationFactory
{
    /**
     * @param string                    $instanceId
     * @param ClientInterface           $redis
     * @param MobileApp\IServiceManager $cloudManager
     * @param UserMapper                $userMapper
     * @param Mailer                    $mailer
     * @param bool                      $mobileDaemonEnabled If true, creating mobile queue creates the daemon's queue:
     *                                                       otherwise, create the direct send queue.
     */
    public function __construct(
        private $instanceId,
        private ClientInterface $redis,
        private MobileApp\IServiceManager $cloudManager,
        private UserMapper $userMapper,
        private Mailer $mailer,
        private bool $mobileDaemonEnabled,
    ) {
        parent::__construct($instanceId, $redis);
    }


    /**
     * The type of queue constructed depends on configuration:
     * * if the mobile daemon is enabled, create its queue
     * * otherwise, create the mobile direct send queue
     *
     * @param int $userId
     * @return QueueInterface
     */
    public function mobile($userId)
    {
        return $this->mobileDaemonEnabled
            ? new Mobile($this->redis, $this->instanceId, $userId)
            : new MobileSend($this->cloudManager, $userId);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function email($userId)
    {
        return new Email($this->userMapper->findById($userId), $this->mailer);
    }
}
