<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\InstanceRegistry\Credentials;

use Alaress\Database\Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\InvalidArgumentException;
use Proxy\Server;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool\App;

abstract class PopulatorTool extends App
{
    /**
     * To save us all this renaming
     */
    protected string $name = '';


    /**
     * This endpoint gets us credentials
     */
    protected string $credentialsEndpoint = '';


    /**
     * Translate config.site_type into something that Instance Registry understands
     * (I guess they're different because andy reasons)
     *
     * This array mapping users config.site_type values as keys,
     * and their equivalent Instance Registry ones as values
     */
    private const ENVIRONMENT_MAP = [
        'stg' => 'staging',
        'prod' => 'production',
        /* Vagrants use a staging environment, I guess?
         */
        'dev' => 'staging',
        // Trials are production instances
        'trial' => 'production',
    ];


    /* Prepared statements
     */
    private const QUERY_UPDATE_CONFIG = 'UPDATE config SET value = :value WHERE name = :name';


    /** @inheritDoc */
    public function run(): void
    {
        /* Basic subclass configuration housekeeping
         */
        if ('' === $this->name) {
            $this->getLogger()->info('Please configure the name of this credentials populator.');
            exit(0);
        }

        if ('' === $this->credentialsEndpoint) {
            $this->getLogger()->info('Please configure endpoint for obtaining credentials.');
            exit(0);
        }

        $this->getLogger()->info(sprintf(
            'Populate %s credentials via Instance Registry',
            $this->name
        ));

        $this->getLogger()->info('');

        /* We need this to determine which environment we are in
         */
        $siteType = $this->getConfig()->get('site_type');

        /* Obtain instance registry configuration vars,
         * cold exiting upon any failures
         */
        [$irUrl, $irApiKey, $puppetCert] = (new Config($this->getLogger()))->fetch($siteType);

        /* Obtain learnosity credentials from instance registry in a set of key-value tuples,
         * remapping siteType into something Instance Registry understands
         */
        $environment = self::ENVIRONMENT_MAP[$siteType] ?? null;
        if (!isset($environment)) {
            $this->getLogger()->error(
                sprintf('Unknown site type "%s": must be one of stg, prod, dev, trial', $siteType),
            );
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $configTuples = $this->fetchCredentials($irUrl, $irApiKey, $puppetCert, $environment);

        /* Shank them into our config table
         */
        $this->updateConfig($configTuples);

        /* We did it! Exit cleanly with 0
         */
        exit(0);
    }


    /**
     * Fetch credentials for this instance from the IR's API
     *
     * Uses Template design pattern so you can subclass this...maybe
     *
     * @param string $irUrl
     * @param string $irApiKey
     * @param string $puppetCert
     * @param string $environment
     * @return array
     * @throws GuzzleException
     * @throws InvalidArgumentException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function fetchCredentials(string $irUrl, string $irApiKey, string $puppetCert, string $environment): array
    {
        $client = new Client(['curl' => Server::getInstance()->buildCurlOptions()]);

        /* This endpoint is listed here: https://manage.schoolbox.cloud/api/documentation
         */
        $endpoint = $this->generateEndpoint($irUrl, $puppetCert, $environment);

        /* Get the credentials from this special endpoint
         */
        $this->getLogger()->info(sprintf('Fetching credentials from Instance Registry API (environment: %s)...', $environment));

        $response = $client->get($endpoint, [
            'headers' => [
                'Authorization' => "Bearer {$irApiKey}",
                'Content-Type'  => 'application/json',
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            $this->getLogger()->error(sprintf(
                'Unable to fetch instance data from Instance Registry: HTTP %s %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            ));

            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        /* access-key-id and secret-access-key are in a json-encoded
         * tuple from that special endpoint
         */
        $content = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        /* Re-map that again into something easily understood,
         * trimming values in case someone copypasted whitespaces/control chars for some reason
         */
        return $this->buildPayload($content);
    }


    /**
     * Configure the url we use to botain credentials
     *
     * @param string $irUrl
     * @param string $puppetCert
     * @param string $environment
     * @return string
     */
    abstract protected function generateEndpoint(string $irUrl, string $puppetCert, string $environment): string;


    /**
     * Re-map that again into something easily understood,
     * trimming values in case someone copypasted whitespaces/control chars for some reason
     */
    abstract protected function buildPayload(array $content): array;


    /**
     * Updates TurnItIn config
     *
     * @param array $configTuples
     * @return void
     * @throws Exception
     */
    protected function updateConfig(array $configTuples): void
    {
        $this->getLogger()->info("Updating instance's config table with fetched credentials...");

        $statement = $this->getDatabaseInternal()->prepareQuery(self::QUERY_UPDATE_CONFIG);

        foreach ($configTuples as $configName => $configValue) {
            $statement->execute([
                'value' => $configValue,
                'name'  => $configName,
            ]);

            $this->getLogger()->debug(sprintf('Updated %s config value', $configName));
        }
    }


    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null; // Doesn't matter
    }
}
