<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Veracross\Base\Endpoint
 */
interface IApi
{
    /**
     * Decide how pig each page size should be
     * Veracross lets you have up to 1000 rows per return
     *
     * Lots of little pages is super slow for them
     */
    public const PAGE_SIZE = 1000;


    /**
     * Concurrently retrieves results for each $endpoint in the $endpoints array,
     * returning an array of guzzle Response instances once all promises resolved.
     */
    public function retrieveConcurrent(string $method, array $endpoints, string $scope, array $params = [], int $pageSize = self::PAGE_SIZE): array;


    /**
     * Iteratively retrieves all pages of data from an endpoint
     *
     * @param string $method
     * @param string $endpoint
     * @param string $scope
     * @param array  $params
     * @return \Generator
     */
    public function retrieve(string $method, string $endpoint, string $scope, array $params = []): \Generator;


    /**
     * Retrieves a single page of data from an endpoint
     *
     * @param string $method
     * @param string $endpoint
     * @param string $scope
     * @param int    $pageNum
     * @param int    $pageSize
     * @param array  $params
     * @return array
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieveOne(string $method, string $endpoint, string $scope, int $pageNum, int $pageSize, array $params = []): array;


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage;


    /**
     * Get decoded DSN pieces
     *
     * @return array
     */
    public function getCredentials(): array;
}
