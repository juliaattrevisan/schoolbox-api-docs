<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing;

use Alaress\WorkerCrew\Worker\PipeWriter;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\CoreContext;
use Schoolbox\Mail\TwigEmailFactory;
use Schoolbox\MobileApp;
use Schoolbox\MobileApp\RegistryMapper;
use Schoolbox\MobileApp\ServiceManagerFactory;
use Schoolbox\Realtime\Mailer;
use Schoolbox\Realtime\Message\RoutePermissionMapper;
use Schoolbox\Realtime\MessageList;
use Schoolbox\Realtime\Routing;
use Schoolbox\Realtime\Routing\Filter;
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


    /**
     * @param IConfigLoader         $conf
     * @param LoggerInterface       $log
     * @param ClientInterface       $client
     * @param RegistryMapper        $registryMapper
     * @param UserMapper            $userMapper
     * @param RoutePermissionMapper $routePermMapper
     */
    public function __construct(
        IConfigLoader $conf,
        LoggerInterface $log,
        ClientInterface $client,
        RegistryMapper $registryMapper,
        UserMapper $userMapper,
        RoutePermissionMapper $routePermMapper
    ) {
        $this->conf = $conf;
        $this->log = $log;
        $this->client = $client;
        $this->registryMapper = $registryMapper;
        $this->userMapper = $userMapper;
        $this->routePermMapper = $routePermMapper;
    }


    /**
     * @param LoopInterface $loop
     * @param string        $instanceId
     * @return Worker
     */
    public function build(LoopInterface $loop, $instanceId)
    {
        // set list factory dependencies
        $cloudManager = (new ServiceManagerFactory($this->conf, $this->log, $this->registryMapper))->build([ MobileApp\Registry::PROVIDER_APPLE, MobileApp\Registry::PROVIDER_GOOGLE ]);

        // factory for configuring instant emails
        $twigFactory = new TwigEmailFactory(new CoreContext($this->conf), $this->log);

        // responsible for draining a list into an email and sending it
        $mailer = new Mailer($twigFactory, $this->log, $this->userMapper);

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
        $txrx = new RouteTXRX($this->userMapper, $sorter);

        return new Routing\Worker($txrx, $loop, $this->log, $writer, $strat);
    }
}