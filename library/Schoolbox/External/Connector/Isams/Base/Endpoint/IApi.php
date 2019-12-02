<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

/**
 * Interface IApi
 *
 * Api and MockApi must implement these methods
 *
 * @package Schoolbox\External\Connector\Isams\Base\Importer
 */
interface IApi
{

    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @param string $filter An XML document representing a filter
     * @return string|null
     */
    public function retrieveDocument(string $filter = ''): ?string;
}
