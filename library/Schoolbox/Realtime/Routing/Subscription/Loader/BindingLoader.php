<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Loader;

use Alaress\Routing\IRouter;
use Psr\Log\LoggerInterface;
use Schoolbox\Notif\Queue\Bindings\GeneratorInterface;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;
use Traversable;


class BindingLoader implements ILoader
{
    /** @var LoggerInterface */
    protected $log;

    /** @var GeneratorInterface */
    private $generator;

    /** @var UserMapper */
    private $userMapper;

    /** @var Folder\Mapper */
    private $folderMapper;

    /**
     * @param GeneratorInterface $generator
     * @param UserMapper         $userMapper
     * @param Folder\Mapper      $folderMapper
     * @param LoggerInterface    $log
     */
    public function __construct(GeneratorInterface $generator, UserMapper $userMapper, Folder\Mapper $folderMapper, LoggerInterface $log)
    {
        $this->log         = $log;
        $this->generator   = $generator;
        $this->userMapper  = $userMapper;
        $this->folderMapper = $folderMapper;
    }


    /**
     * @param IRouter $router
     * @return $this
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

        foreach ($this->getRedirects() as $from => $to) {
            $this->log->info("Adding redirect `$from` -> `$to`");
            $router->add($from, $to, true);
        }

        foreach ($this->getInheritances() as $from => $to) {
            $this->log->info("Adding inheritance `$from` -> `$to`");
            $router->add($from, $to, true);
        }

        return $this;
    }


    /**
     * @param int $userId
     * @return string[]
     */
    public function getUserSubscriptions($userId)
    {
        $userRoutes  = [];

        $bindings = $this->generator->generateBindingsForUser($this->userMapper->findById($userId));

        foreach ($bindings as $prefix => $data) {
            foreach ($data as $target) {
                $binding = "*.notify.$prefix.$target";
                $userRoutes[$binding] = true;
            }
        }

        return $userRoutes;
    }


    /**
     * @return Traversable
     */
    public function getRedirects()
    {
        $stmt = $this->userMapper->getDatabase()->query('SELECT `from`, `to` FROM msg_redirect');

        while (list ($from, $to) = $stmt->fetch(\PDO::FETCH_NUM)) {
            yield $from => $to;
        }
    }


    /**
     * Yield the inheritance of one folder from another (which will have the effect of cascading up to the NNIAN)
     * The direction of the yield is child => parent.
     *
     * @return Traversable
     */
    private function getInheritances()
    {
        /** @var Folder\Model[] $folders */
        $folders = $this->folderMapper->findByColumn('inherit', true);

        $perms = [ UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL ];

        foreach ($folders as $folder) {
            // we have to manually map each access level (sigh)
            foreach ($perms as $p) {
                $from = "*.notify.service.1.{$folder->getId()}.{$p}.#";
                $to   = "*.notify.service.1.{$folder->getParent()->getId()}.{$p}.#";

                yield $from => $to;
            }
        }
    }

}