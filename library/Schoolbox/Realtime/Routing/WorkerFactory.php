<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\WorkerCrew\Worker\PipeWriter;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\EmailFactory;
use Schoolbox\MobileApp;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\Realtime\Mailer;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\MessageList;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Routing\Filter;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\UserMapper;

class WorkerFactory
{
    /** @var IConfigLoader */
    private $conf;

    /** @var LoggerInterface */
    private $log;

    /** @var RegistryMapper */
    private $registryMapper;

    /** @var UserMapper */
    private $userMapper;

    /** @var RoutePermissionMapper */
    private $routePermMapper;

    /** @var ClientInterface */
    private $client;

    /** @var Schoolbox\Instance */
    private $instance;

    private EmailFactory $emailFactory;


    /**
     * @param IConfigLoader         $conf
     * @param LoggerInterface       $log
     * @param ClientInterface       $client
     * @param RegistryMapper        $registryMapper
     * @param UserMapper            $userMapper
     * @param RoutePermissionMapper $routePermMapper
     * @param \Schoolbox\Instance   $instance
     * @param FilesystemProxy       $filesystemProxy
     * @param EmailFactory          $emailFactory
     */
    public function __construct(
        IConfigLoader $conf,
        LoggerInterface $log,
        ClientInterface $client,
        RegistryMapper $registryMapper,
        UserMapper $userMapper,
        RoutePermissionMapper $routePermMapper,
        Schoolbox\Instance $instance,
        private FilesystemProxy $filesystemProxy,
        EmailFactory $emailFactory
    ) {
        $this->conf = $conf;
        $this->log = $log;
        $this->client = $client;
        $this->registryMapper = $registryMapper;
        $this->userMapper = $userMapper;
        $this->routePermMapper = $routePermMapper;
        $this->instance = $instance;
        $this->emailFactory = $emailFactory;
    }


    /**
     * @param LoopInterface $loop
     * @param string        $instanceId
     * @return Worker
     */
    public function build(LoopInterface $loop, $instanceId)
    {
        // set list factory dependencies
        $cloudManager = new MobileApp\ServiceManager(
            $this->log,
            $this->registryMapper,
            $this->instance,
            $this->conf->get('title'),
            $this->filesystemProxy,
            MobileApp\ServiceManager::SERVICE_TYPE_BASED_ON_INSTANCE_TYPE,
        );

        // responsible for draining a list into an email and sending it (only include reply-to headers if config allows)
        $mailer = new Mailer($this->emailFactory, $this->log, $this->userMapper, $this->conf->get('mail_replyto_enabled'));

        // build a list factory
        $listFactory = new MessageList\PublicationFactory($instanceId, $this->client, $cloudManager, $this->userMapper, $mailer);

        // obtain filter list
        $filter = (new Filter\FilterFactory($listFactory, $this->conf))->build();

        // setup a sorter
        $sorter = new QueueSorter($filter, $this->log, $listFactory, $this->routePermMapper);

        // use the route strat
        $strat = new Routing\DispatchStrategy();

        // we open stream(3) as our data output stream
        $writer = new PipeWriter($strat, $this->log, fopen('php://fd/3', 'wb'));

        // worker-side: dependencies used
        $txrx = RouteTXRX::buildReceiver($this->userMapper, $sorter);

        return new Routing\Worker($txrx, $loop, $this->log, $writer, $strat);
    }
}
