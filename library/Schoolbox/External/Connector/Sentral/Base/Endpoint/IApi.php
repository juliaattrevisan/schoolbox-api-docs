<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

/**
 * Interface IApi
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
interface IApi
{

    /**
     * Retrieves data from an endpoint
     *
     * @return string|null
     */
    public function retrieveDocument(): ?string;
}
