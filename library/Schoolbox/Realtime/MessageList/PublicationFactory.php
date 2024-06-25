<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Queue\QueueInterface;
use Predis\ClientInterface;
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
     * @param string                    $instanceId         The instance for which this factory is created.
     * @param ClientInterface           $redis              Redis client: data store for messages.
     * @param UserMapper                $userMapper         User mapper: required for finding user's email address.
     * @param Mailer                    $mailer             Mailer class: required for sending emails.
     * @param Mobile\IQueueFactory      $mobileQueueFactory The factory which may build a different type of mobile
     *                                                      queue, dependent on configuration.
     */
    public function __construct(
        string $instanceId,
        ClientInterface $redis,
        private UserMapper $userMapper,
        private Mailer $mailer,
        private Mobile\IQueueFactory $mobileQueueFactory,
    ) {
        parent::__construct($instanceId, $redis);
    }


    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function mobile($userId)
    {
        return $this->mobileQueueFactory->build($userId);
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
