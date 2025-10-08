<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Credentials;

/**
 * Obtains credentials from $credentialsEndpoint,
 * configured to suit your unique use case via
 * generateEndpoint() and buildPayload()
 */
class PopulatorTool extends \Schoolbox\InstanceRegistry\Credentials\PopulatorTool
{
    /**
     * These are the credentials we need; purely cosmetic
     *
     * @var string
     */
    protected string $name = 'Learnosity';


    /**
     * This endpoint gets us learnosity credentials specially
     */
    protected string $credentialsEndpoint = '%s/api/secret/learnosity/%s';


    /**
     * Configure the url we use to obtain credentials
     *
     * @param string $irUrl
     * @param string $environment
     * @return string
     */
    protected function generateEndpoint(string $irUrl, string $environment): string
    {
        return sprintf(
            $this->credentialsEndpoint,
            $irUrl,
            $environment
        );
    }


    /**
     * Re-map that again into something easily understood,
     * trimming values in case someone copypasted whitespaces/control chars for some reason
     */
    protected function buildPayload(array $content): array
    {
        /* These go into config table (name, value)
         */
        return [
            'learnosity_consumer_key' => trim((string)$content['access-key-id']),
            'learnosity_consumer_secret' => trim((string)$content['secret-access-key'])
        ];
    }
}
