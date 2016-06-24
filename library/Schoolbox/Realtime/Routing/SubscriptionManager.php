<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing;


use Alaress\Routing\Router;
use Psr\Log\LoggerInterface;
use React\Stomp\Client;
use Schoolbox\Notif\Queue\Bindings\Generator;
use Schoolbox\UserMapper;

class SubscriptionManager
{

    /**
     * @var Router
     */
    protected $router;

    /**
     * @var LoggerInterface
     */
    protected $log;


    /**
     * @param Router          $router
     * @param LoggerInterface $log
     */
    public function __construct(Router $router, LoggerInterface $log)
    {
        $this->router = $router;
        $this->log = $log;
    }


    /**
     * @param Generator  $generator
     * @param UserMapper $userMapper
     * @return void
     */
    public function loadSubscriptions(Generator $generator, UserMapper $userMapper)
    {
        $userIds = $userMapper->findAllIds();
        // $userIds = [ 230, 151 ];

        $this->log->info(sprintf("Subscribing %d users", count($userIds)));

        foreach ($userIds as $userId) {
            $user   = $userMapper->findById($userId);
            $routes = $generator->generateBindingsForUser($user);

            foreach ($routes as $prefix => $bindings) {
                foreach ($bindings as $binding) {
                    // $route = "*.notify.$instanceId.$prefix.$binding";
                    $route = "*.notify.$prefix.$binding";
                    $this->router->add($route, $userId, true);
                    // $log->debug("Subscribed user $userId to $route");
                }
            }
            $this->log->debug("Routes added for user {$user->getUsername()} ($userId)");
        }

        $this->log->debug("Subscriptions loaded");
    }


    /**
     * @param string $topic
     * @return array
     */
    public function getSubscribers($topic)
    {
        return array_keys(array_filter($this->router->get($topic)));
    }

}
