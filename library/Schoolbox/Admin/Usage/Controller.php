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
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param Redis $cache
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private IConnector $database,
        private Redis $cache
    ) {
    }


    /**
     * Container for all the graphs and tables
     * These are graphs for Global Usage across the system
     */
    public function getIndex(): void
    {
        $this->response = $this->responseFromTemplate('@adminUsage/global.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminUsage.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminUsage.getIndex')),
            'isS3Adapter' => $this->isS3Adapter(),
        ]);
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
        $crumbs = (new Subnav($this->getConfigLoader(), $this->getAuthorizer(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminUsage.getIndex'));
        $crumbs[] = [$user->getFullName(), ''];

        $this->response = $this->responseFromTemplate('@adminUsage/specificUser.twig', [
            'pageCurrent' => $this->routeGenerator->generate('adminUsage.getIndex'),
            'allBreadcrumbs' => $crumbs,
            'isS3Adapter' => $this->isS3Adapter(),
            'user' => $user
        ]);
    }


    /**
     * Generate payload for System Usage
     */
    public function getSystemPayload(): void
    {
        //get redis cache result for Regenerate::CACHE_USAGE_SYSTEM, serve to user
        $callback = function () {
            return [
                'time' => time(),
                'data' => (new Graph\GlobalUsers\SystemPayload($this->database))->generate()
            ];
        };
        $payload = $this->cache->cacheFunctionCall(Regenerate::CACHE_USAGE_SYSTEM, $callback, '', Regenerate::CACHE_TIMEOUT);

        $this->response = $this->responseAsJson($payload);
    }


    /**
     * Generate payload for Role Usage
     */
    public function getRolePayload(): void
    {
        //get redis cache result for Regenerate::CACHE_USAGE_ROLE, serve to user
        $callback = function () {
            return [
                'time' => time(),
                'data' => (new Graph\GlobalUsers\RolePayload($this->database))->generate()
            ];
        };
        $payload = $this->cache->cacheFunctionCall(Regenerate::CACHE_USAGE_ROLE, $callback, '', Regenerate::CACHE_TIMEOUT);

        $this->response = $this->responseAsJson($payload);
    }


    /**
     * Generate payload for Total Usage
     */
    public function getTotalPayload(): void
    {
        $payload = (new Graph\GlobalUsers\TotalPayload($this->database, $this->getConfigLoader()->get('storage_dir')))->generate();

        $this->response = $this->responseAsJson($payload);
    }


    /**
     * Generate payload for User Usage
     */
    public function getUserPayload(): void
    {
        //get redis cache result for Regenerate::CACHE_USAGE_USER, serve to user
        $callback = function () {
            return [
                'time' => time(),
                'data' => (new Graph\GlobalUsers\UserPayload($this->database))->generate()
            ];
        };
        $payload = $this->cache->cacheFunctionCall(Regenerate::CACHE_USAGE_USER, $callback, '', Regenerate::CACHE_TIMEOUT);

        $this->response = $this->responseAsJson($payload);
    }


    /**
     * Generate payload for User Usage
     */
    public function getFileTypePayload(): void
    {
        //get redis cache result for Regenerate::CACHE_USAGE_FILETYPE, serve to user
        $callback = function () {
            return [
                'time' => time(),
                'data' => (new Graph\GlobalUsers\FileTypePayload($this->database))->generate()
            ];
        };
        $payload = $this->cache->cacheFunctionCall(Regenerate::CACHE_USAGE_FILETYPE, $callback, '', Regenerate::CACHE_TIMEOUT);

        $this->response = $this->responseAsJson($payload);
    }


    /**
     * Generate payload for System Usage
     *
     * @param User $user
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getSpecificUserSystemPayload(User $user): void
    {
        $payload = (new Graph\SpecificUser\SystemPayload($this->database))->generate($user);

        $this->response = $this->responseAsJson($payload);
    }


    /**
     * Generate payload for Total Usage
     *
     * @param User $user
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getSpecificUserTotalPayload(User $user): void
    {
        $payload = (new Graph\SpecificUser\TotalPayload($this->database))->generate($user);

        $this->response = $this->responseAsJson($payload);
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
        $payload = (new Graph\SpecificUser\FilePayload($this->database))->generate($user);

        $this->response = $this->responseAsJson($payload);
    }


    /**
     * Generate payload for User Usage
     * @param User $user
     * @throws \Alaress\Database\Exception
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     */
    public function getSpecificUserFileTypePayload(User $user): void
    {
        $payload = (new Graph\SpecificUser\FileTypePayload($this->database))->generate($user);

        $this->response = $this->responseAsJson($payload);
    }

    /**
     * clear the current usage cache and regenerate the cache
     * no response data... unless there are exceptions
     */
    public function postRegenerateUsage(): void
    {
        $regenerate = new Regenerate($this->cache, $this->database);
        $regenerate->regenerateUsage();
    }
}
