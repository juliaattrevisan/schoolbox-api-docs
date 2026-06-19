<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\ToolsDaemon;

use GuzzleHttp\ClientInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Url\Uri;

/**
 * Factory for building tools daemon clients.
 */
class ClientFactory
{
    /**
     * Constructor
     *
     * @param ClientInterface $httpClient HTTP client
     * @param IConfigLoader   $config     Schoolbox configuration
     */
    public function __construct(
        private ClientInterface $httpClient,
        private IConfigLoader $config,
    ) {
    }

    /**
     * Build the client.
     *
     * @return IClient
     */
    public function build(): IClient
    {
        $host = $this->config->get(ConfigMapper::NAME_TOOLSD_HOST);
        if (!empty($host)) {
            return new Client($this->httpClient, new Uri($host));
        }
        return new NullClient();
    }
}
