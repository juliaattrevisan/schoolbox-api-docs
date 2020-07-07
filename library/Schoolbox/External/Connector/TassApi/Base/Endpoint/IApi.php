<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
interface IApi
{

    /**
     * Retrieves a data document representing the result from the API call.
     *
     * @return string|null
     */
    public function retrieveDocument(): ?string;
}
