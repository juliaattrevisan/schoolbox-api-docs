<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Presidio;

use GuzzleHttp\Client as HttpClient;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;

/**
 * Factory for `\Schoolbox\Presidio\Client` instances.
 */
class ClientFactory
{
    /**
     * Constructor
     *
     * @param IConfigLoader $config     Configuration
     * @param HttpClient    $httpClient HTTP client used by Presidio
     */
    public function __construct(private IConfigLoader $config, private HttpClient $httpClient)
    {
    }

    /**
     * Build a Presidio client instance, if its analysis and anonymisation
     * services URLs are configured.
     *
     * @return Client|null
     */
    public function build(): ?Client
    {
        $analysisHost      = trim((string)$this->config->get(ConfigMapper::NAME_PRESIDIO_ANALYSIS_HOST));
        $anonymisationHost = trim((string)$this->config->get(ConfigMapper::NAME_PRESIDIO_ANONYMISATION_HOST));
        if ($analysisHost !== '' && $anonymisationHost !== '') {
            return new Client($this->httpClient, $analysisHost, $anonymisationHost);
        }
        return null;
    }
}
