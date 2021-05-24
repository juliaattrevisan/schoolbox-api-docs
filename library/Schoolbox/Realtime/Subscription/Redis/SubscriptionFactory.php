<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Subscription\Redis;

use Predis\ClientException;
use Predis\ClientInterface;
use Schoolbox\Realtime\Subscription\ISubscription;
use Schoolbox\Realtime\Subscription\ISubscriptionFactory;
use Alaress\Redis\Async;

class SubscriptionFactory implements ISubscriptionFactory
{

    /** @var Async\Client */
    private $asyncClient;

    /** @var Async\EventListener */
    private $listener;

    /** @var ClientInterface */
    private $syncClient;


    /**
     * @param ClientInterface     $syncClient
     * @param Async\Client        $asyncClient
     */
    public function __construct(ClientInterface $syncClient, Async\Client $asyncClient)
    {
        $this->syncClient  = $syncClient;
        $this->asyncClient = $asyncClient;
    }


    /**
     * @param Async\EventListener $listener
     * @return $this
     */
    public function setListener(Async\EventListener $listener)
    {
        $this->listener = $listener;
        return $this;
    }


    /**
     * @param string $instanceId
     * @param int    $userId
     * @throws ClientException
     * @return ISubscription
     */
    public function build($instanceId, $userId)
    {
        return new Subscription(
            $instanceId,
            $userId,
            $this->syncClient,
            $this->asyncClient,
            $this->listener,
            new UnreadRetriever($this->syncClient)
        );
    }
}
