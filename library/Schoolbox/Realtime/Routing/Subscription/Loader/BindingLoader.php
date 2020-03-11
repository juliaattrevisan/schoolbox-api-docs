<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription\Loader;

use Alaress\Async\Notification\Instance\Instance;
use Alaress\Routing\IRouter;
use PDO;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper\Exception;
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
    public function __construct(
        GeneratorInterface $generator,
        UserMapper $userMapper,
        Folder\Mapper $folderMapper,
        LoggerInterface $log
    ) {
        $this->log          = $log;
        $this->generator    = $generator;
        $this->userMapper   = $userMapper;
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
            try {
                $user = $this->userMapper->findById($userId);
            } catch (Exception $e) {
                // should not happen, but just in case
                continue;
            }
            $routeCount = 0;
            foreach (array_keys($this->getUserSubscriptions($userId)) as $userSub) {
                $router->add($userSub, $userId, true);
                ++$routeCount;
                ++$totalRoutes;
            }
            $this->log->debug(
                sprintf(
                    "$progressPattern %3d routes loaded for user %5d (%s)",
                    $i + 1,
                    $userCount,
                    $routeCount,
                    $userId,
                    $user->getUsername()
                )
            );
        }

        $b = microtime(true);

        $this->log->debug(
            sprintf(
                "%d subscriptions loaded for %d users in %0.3f seconds",
                $totalRoutes,
                $userCount,
                ($b - $a)
            )
        );

        foreach ($this->getRedirects() as $from => $to) {
            $this->log->info("Adding redirect `$from` -> `$to`");
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
        $baseUserRoute = "*.%s.user.%d.#";
        $userRoutes    = [
            sprintf($baseUserRoute, Instance::DELIVERY_ALERT, $userId)   => true,
            sprintf($baseUserRoute, Instance::DELIVERY_INSTANT, $userId) => true,
        ];

        try {
            $bindings = $this->generator->generateBindingsForUser($this->userMapper->findById($userId));
        } catch (Exception $e) {
            // returning empty array to remove its subscription
            return [];
        }

        foreach ($bindings as $prefix => $data) {
            foreach ($data as $target) {
                $binding              = sprintf("*.%s.%s.%s", Instance::DELIVERY_NOTIFY, $prefix, $target);
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

        while ([$from, $to] = $stmt->fetch(PDO::FETCH_NUM)) {
            yield $from => $to;
        }

        /** @var Folder\Model[] $folders */
        $folders = $this->folderMapper->findByColumn('inherit', true);

        $perms = [UserAccess::ACCESS_READ, UserAccess::ACCESS_WRITE, UserAccess::ACCESS_FULL];

        $baseServiceRoute = "*.%s.service.1.%d.%s.#";
        foreach ($folders as $folder) {
            // we have to manually map each access level (sigh)
            foreach ($perms as $p) {
                $from = sprintf($baseServiceRoute, Instance::DELIVERY_NOTIFY, $folder->getId(), $p);
                $to   = sprintf($baseServiceRoute, Instance::DELIVERY_NOTIFY, $folder->getParent()->getId(), $p);
                yield $from => $to;
            }
        }
    }
}
