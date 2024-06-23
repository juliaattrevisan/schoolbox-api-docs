<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;
use Predis\ClientInterface;
use Schoolbox\MobileApp;
use Schoolbox\Realtime\Mailer;
use Schoolbox\UserMapper;

/**
 * Factory for message "queues" for direct publication.
 */
class PublicationFactory extends ListFactory implements IPublicationFactory
{
    /**
     * Constructor
     *
     * @param string                    $instanceId          The instance for which this factory is created.
     * @param ClientInterface           $redis               Redis client: data store for messages.
     * @param MobileApp\IServiceManager $cloudManager        Service required for direct send of mobile messages:
     *                                                       responsible for actually sending to a delivery agent.
     * @param UserMapper                $userMapper          User mapper: required for finding user's email address.
     * @param Mailer                    $mailer              Mailer class: required for sending emails.
     * @param bool                      $mobileDaemonEnabled If true, creating mobile queue creates the daemon's queue:
     *                                                       otherwise, create the direct send queue.
     *                                                       It's expected this option will be removed once the mobile
     *                                                       daemon is verified to be stable.
     */
    public function __construct(
        private string $instanceId,
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
