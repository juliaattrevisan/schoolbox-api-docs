<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Usage;

use Alaress\Database\Connector\IConnector;
use Alaress\Redis;
use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\FilesystemFactory;
use Schoolbox\User;

/**
 * Class Controller
 *
 * @package Schoolbox\Admin\Usage
 */
class Controller extends Base
{

    const CACHE_TIMEOUT = 86400;
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param Redis $cache
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private Redis $cache
    )
    {}


    /**
     * Container for all the graphs and tables
     * These are graphs for Global Usage across the system
     */
    public function getIndex(): void
    {
        $this->getResponse()->content->set($this->buildHtmlOutput('@adminUsage/global.twig', [
            'pageCurrent' => $this->router->generate('adminUsage.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminUsage.getIndex')),
            'isS3Adapter' => $this->isS3Adapter(),
        ]));
    }


    /**
     * Decide if we're using the S3 adapter
     * This is basically infinite storage so there's no point representing this data in a graph
     * Tabulate it instead, in frontend
     *
     * @return int
     */
    private function isS3Adapter(): int
    {
        return (int)($this->getConfigLoader()->get(ConfigMapper::NAME_OTFS_ADAPTER) === FilesystemFactory::FILE_SYSTEM_ADAPTER_AWS_S3);
    }


    /**
     * Container for all the graphs and tables
     * These are graphs for a user's Specific Usage across the system
     * @param User $user
     */
    public function getSpecificUser(User $user): void
    {
        $crumbs = (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminUsage.getIndex'));
        $crumbs[] = [$user->getFullName(), ''];

        $this->getResponse()->content->set($this->buildHtmlOutput('@adminUsage/specificUser.twig', [
            'pageCurrent' => $this->router->generate('adminUsage.getIndex'),
            'allBreadcrumbs' => $crumbs,
            'isS3Adapter' => $this->isS3Adapter(),
            'user' => $user
        ]));
    }


    /**
     * Generate payload for System Usage
     */
    public function getSystemPayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        //get redis cache result, serve to user
        $callback = function ($database) {
            return (new Graph\GlobalUsers\SystemPayload($database))->generate();
        };
        $payload = $this->cache->cacheFunctionCall('system_usage_system', $callback, $database, self::CACHE_TIMEOUT);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for Role Usage
     */
    public function getRolePayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        //get redis cache result, serve to user
        $callback = function ($database) {
            return (new Graph\GlobalUsers\RolePayload($database))->generate();
        };
        $payload = $this->cache->cacheFunctionCall('system_usage_role', $callback, $database, self::CACHE_TIMEOUT);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for Total Usage
     */
    public function getTotalPayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        //get redis cache result, serve to user
        $callback = function ($database) {
            return (new Graph\GlobalUsers\TotalPayload($database, $this->getConfigLoader()->get('storage_dir')))->generate();
        };
        $payload = $this->cache->cacheFunctionCall('system_usage_total', $callback, $database, self::CACHE_TIMEOUT);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for User Usage
     */
    public function getUserPayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        //get redis cache result, serve to user
        $callback = function ($database) {
            return (new Graph\GlobalUsers\UserPayload($database))->generate();
        };
        $payload = $this->cache->cacheFunctionCall('system_usage_user', $callback, $database, self::CACHE_TIMEOUT);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for User Usage
     */
    public function getFileTypePayload(): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        //get redis cache result, serve to user
        $callback = function ($database) {
            return (new Graph\GlobalUsers\FileTypePayload($database))->generate();
        };
        $payload = $this->cache->cacheFunctionCall('system_usage_fileType', $callback, $database, self::CACHE_TIMEOUT);

        $deleted = $this->cache->dropKeyCache('system_usage_fileType', $database);
        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for System Usage
     *
     * @param User $user
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getSpecificUserSystemPayload(User $user): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        $payload = (new Graph\SpecificUser\SystemPayload($database))->generate($user);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for Total Usage
     *
     * @param User $user
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getSpecificUserTotalPayload(User $user): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        $payload = (new Graph\SpecificUser\TotalPayload($database))->generate($user);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for File Usage
     *
     * @param User $user
     * @throws \Alaress\Database\Exception
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getSpecificUserFilePayload(User $user): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        $payload = (new Graph\SpecificUser\FilePayload($database))->generate($user);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }


    /**
     * Generate payload for User Usage
     * @param User $user
     * @throws \Alaress\Database\Exception
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getSpecificUserFileTypePayload(User $user): void
    {
        /** @var IConnector $database */
        $database = $this->getDi()->get('databaseInternal');

        $payload = (new Graph\SpecificUser\FileTypePayload($database))->generate($user);

        $this->getResponse()->content->set($this->buildJsonOutput($payload));
    }
}
