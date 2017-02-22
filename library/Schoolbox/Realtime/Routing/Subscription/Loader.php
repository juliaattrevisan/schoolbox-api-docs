<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription;

use Alaress\Routing\IRouter;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\Queue\Bindings\GeneratorInterface;
use Schoolbox\UserMapper;

class Loader
{
    /** @var LoggerInterface */
    protected $log;

    /** @var GeneratorInterface */
    private $generator;

    /** @var UserMapper */
    private $userMapper;


    /**
     * @param GeneratorInterface $generator
     * @param UserMapper         $userMapper
     * @param LoggerInterface    $log
     */
    public function __construct(GeneratorInterface $generator, UserMapper $userMapper, LoggerInterface $log)
    {
        $this->log        = $log;
        $this->generator  = $generator;
        $this->userMapper = $userMapper;
    }


    /**
     * @param IRouter $router
     * @return void
     */
    public function loadSubscriptions(IRouter $router)
    {
        $userIds = $this->userMapper->findAllIds();
        // $userIds = [ 230, 151 ];

        $userCount       = count($userIds);
        $totalRoutes     = 0;
        $ucLen           = strlen("$userCount");
        $progressPattern = "[%{$ucLen}d/%{$ucLen}d]";

        $this->log->info(sprintf("Subscribing %d users", $userCount));

        $a = microtime(true);

        foreach ($userIds as $i => $userId) {
            $user       = $this->userMapper->findById($userId);
            $routes     = $this->generator->generateBindingsForUser($user);
            $routeCount = 0;

            foreach ($routes as $prefix => $bindings) {
                foreach ($bindings as $binding) {
                    // $route = "*.notify.$instanceId.$prefix.$binding";
                    $route = "*.notify.$prefix.$binding";
                    $router->add($route, $userId, true);
                    ++$routeCount;
                    ++$totalRoutes;
                }
            }
            $this->log->debug(sprintf("$progressPattern %3d routes loaded for user %5d (%s)", $i+1, $userCount, $routeCount, $userId, $user->getUsername()));
        }

        $b = microtime(true);

        $this->log->debug(sprintf("%d subscriptions loaded for %d users in %0.3f seconds", $totalRoutes, $userCount, ($b - $a)));
    }

}