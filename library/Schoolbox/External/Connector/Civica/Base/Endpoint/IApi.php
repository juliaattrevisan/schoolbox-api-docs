<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint
 */
interface IApi
{
    /**
     * Decide how big each page size should be
     * Civica's default response is 1000
     * (Note this has been increased to 3500 because of civica not loading all the data correctly, there might be a fix,
     * but we don't know when they will implement it yet, so for the time being increasing this will allow the data to flow
     * through, and it shouldn't heavily impact the performance, the data that wasn't truncated still return the same size of payload)
     */
    public const PAGE_SIZE = 3500;


    /**
     * School-specific endpoint access
     *
     * Retrieve data using the SUDS Control Panel's schoolId field
     * This is allegedly used for *some* (but not all) endpoints
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param int $pageSize
     * @return \Generator
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieveForSchool(string $method, string $endpoint, array $params = [], int $pageSize = self::PAGE_SIZE): \Generator;


    /**
     * Jurisdictional endpoint access
     *
     * Retrieve data using a hardcoded 0 as a schoolId, which grants you
     * access to all the /Jurisdiction endpoints...because Civica reasons.
     *
     * This is allegedly used for *some* (but not all) endpoints
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param int $pageSize
     * @return \Generator
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function retrieveForJurisdiction(string $method, string $endpoint, array $params = [], int $pageSize = self::PAGE_SIZE): \Generator;


    /**
     * @return ApiUsage
     */
    public function getUsage(): ApiUsage;
}
