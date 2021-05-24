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
    /** @var MobileApp\IServiceManager */
    private $cloudManager;

    /** @var UserMapper */
    private $userMapper;

    /** @var Mailer */
    private $mailer;

    /**
     * @param string                    $instanceId
     * @param ClientInterface           $redis
     * @param MobileApp\IServiceManager $cloudManager
     * @param UserMapper                $userMapper
     * @param Mailer                    $mailer
     */
    public function __construct(
        $instanceId,
        ClientInterface $redis,
        MobileApp\IServiceManager $cloudManager,
        UserMapper $userMapper,
        Mailer $mailer
    ) {
        parent::__construct($instanceId, $redis);
        $this->cloudManager = $cloudManager;
        $this->userMapper   = $userMapper;
        $this->mailer       = $mailer;
    }



    /**
     * @param int $userId
     * @return QueueInterface
     */
    public function mobile($userId)
    {
        return new Mobile($this->cloudManager, $userId);
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
