<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Alaress\Cache\CommandLineTool;

use Alaress\Redis;
use External_Class;
use External_ClassInterface;
use External_Timetable;
use External_TimetableInterface;
use External_User;
use External_UserInterface;
use ReflectionClass;
use ReflectionException;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;

/**
 * Command-line tool for clearing the cache for all external connectors.
 *
 * Clears the cache for all calls to any method defined on the following
 * external connector interfaces:
 * * `External_ClassInterface`
 * * `External_TimetableInterface`
 * * `External_UserInterface`
 */
class ClearExternal extends App
{
    /**
     * A list of interfaces to clear cache for, and a mapping to the actual
     * class name which is used to build their cache key.
     *
     * @var array<class-string, class-string> key: interface class name,
     *                                        value: concrete class name
     */
    private const array INTERFACES = [
        External_UserInterface::class      => External_User::class,
        External_ClassInterface::class     => External_Class::class,
        External_TimetableInterface::class => External_Timetable::class,
    ];

    /**
     * @return void
     */
    public function run(): void
    {
        $logger        = $this->getLogger();
        $cacheInstance = Redis::getCacheInstance();
        $successCount  = 0;
        $failureCount  = 0;
        foreach (self::INTERFACES as $interface => $class) {
            $logger->notice(sprintf('Clearing cache for interface "%s", class "%s"', $interface, $class));
            $reflection = new ReflectionClass($interface);
            $interfaceSuccessCount = 0;
            $interfaceFailureCount = 0;
            foreach ($reflection->getMethods() as $method) {
                if ($method->isPublic()) {
                    // Create a cache object for this method, and clear it
                    $namespace = $class . '.' . $method->getName();
                    $logger->info(sprintf('. clearing cache for method "%s', $namespace));
                    if ($cacheInstance->clearCache($namespace)) {
                        $logger->info(sprintf('.. cache for method "%s cleared successfully', $namespace));
                        $interfaceSuccessCount++;
                    } else {
                        $logger->warning(sprintf('.. cache for method "%s could not be cleared, for some reason', $namespace));
                        $interfaceFailureCount++;
                    }
                }
            }
            if ($interfaceFailureCount > 0) {
                $logger->warning(
                    sprintf(
                        '. %d method caches cleared, %d not cleared',
                        $interfaceSuccessCount,
                        $interfaceFailureCount,
                    ),
                );
                $failureCount++;
            } else {
                $logger->notice(
                    sprintf('. %d method caches cleared', $interfaceSuccessCount),
                );
                $successCount++;
            }
        }

        if ($failureCount > 0) {
            $logger->warning(
                sprintf(
                    '%d interface caches fully cleared, %d not cleared',
                    $successCount,
                    $failureCount,
                ),
            );
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        } else {
            $logger->notice(
                sprintf('%d interface caches fully cleared', $successCount),
            );
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }
    }

    /**
     * It doesn't matter whether this tool is run as root or not.
     *
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
}
