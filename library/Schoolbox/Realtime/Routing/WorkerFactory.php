<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\Jitter\IJitter;
use Alaress\WorkerCrew\Worker\PipeWriter;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\EmailFactory;
use Schoolbox\Realtime\Mailer;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\MessageList;
use Schoolbox\Realtime\Routing;
use Schoolbox\UserMapper;
use Schoolbox\Realtime\Subscription\Redis\UnreadRetriever;

class WorkerFactory
{
    /** @var IConfigLoader */
    private $conf;

    /** @var LoggerInterface */
    private $log;

    /** @var UserMapper */
    private $userMapper;

    /** @var RoutePermissionMapper */
    private $routePermMapper;

    /** @var ClientInterface */
    private $client;

    private EmailFactory $emailFactory;


    /**
     * @param IConfigLoader                    $conf
     * @param LoggerInterface                  $log
     * @param ClientInterface                  $client
     * @param UserMapper                       $userMapper
     * @param RoutePermissionMapper            $routePermMapper
     * @param EmailFactory                     $emailFactory
     * @param IJitter                          $jitter
     * @param MessageList\Mobile\IQueueFactory $mobileQueueFactory
     */
    public function __construct(
        IConfigLoader $conf,
        LoggerInterface $log,
        ClientInterface $client,
        UserMapper $userMapper,
        RoutePermissionMapper $routePermMapper,
        EmailFactory $emailFactory,
        private IJitter $jitter,
        private MessageList\Mobile\IQueueFactory $mobileQueueFactory,
    ) {
        $this->conf = $conf;
        $this->log = $log;
        $this->client = $client;
        $this->userMapper = $userMapper;
        $this->routePermMapper = $routePermMapper;
        $this->emailFactory = $emailFactory;
    }


    /**
     * @param LoopInterface $loop
     * @param string        $instanceId
     * @return Worker
     */
    public function build(LoopInterface $loop, $instanceId)
    {
        // responsible for draining a list into an email and sending it (only include reply-to headers if config allows)
        $mailer = new Mailer($this->emailFactory, $this->log, $this->userMapper, $this->conf->get('mail_replyto_enabled'));

        // build a list factory
        $listFactory = new MessageList\PublicationFactory(
            $instanceId,
            $this->client,
            $this->userMapper,
            $mailer,
            $this->mobileQueueFactory,
        );

        // obtain filter list
        $filter = (new Filter\FilterFactory($listFactory, $this->conf))->build();

        // setup a sorter
        $sorter = new QueueSorter($filter, $this->log, $listFactory, $this->routePermMapper, $loop, $this->jitter);

        // use the route strat
        $strat = new Routing\DispatchStrategy();

        // we open stream(3) as our data output stream
        $writer = new PipeWriter($strat, $this->log, fopen('php://fd/3', 'wb'));

        // worker-side: dependencies used
        $txrx = RouteTXRX::buildReceiver($this->userMapper, $sorter);

        return new Routing\Worker($txrx, $loop, $this->log, $writer, $strat);
    }
}
