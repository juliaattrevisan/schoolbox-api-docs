#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Fetches Learnosity credentials from IR
 * Populates instance's database
 */
$app = new class extends CliTool\App
{
    /**
     * Translate config.site_type into something that Instance Registry understands
     * (I guess they're different because andy reasons)
     *
     * This array mapping users config.site_type values as keys,
     * and their equivalent Instance Registry ones as values
     */
    private const ENVIRONMENT_MAP = [
        'stg' => 'staging',
        /* TODO These two might be wrong?
         *  Need to check in their respective environs.
         */
        'dev' => 'dev',
        'prod' => 'prod'
    ];


    /** @inheritDoc */
    public function run(): void
    {
        /* Obtain instance registry configuration vars,
         * cold exiting upon any failures
         */
        [$irUrl, $irApiKey, $siteType] = $this->getIrConfig();

        /* Remap siteType into something Instance Registry understands
         */
        $siteType = self::ENVIRONMENT_MAP[$siteType];

        /* Obtain learnosity credentials from instance registry
         */
        [$learnosityConsumerKey, $learnosityConsumerSecret] = $this->fetchCredentials($irUrl, $irApiKey, $siteType);

        /* Shank them into our config table
         */
        $this->updateConfig($learnosityConsumerKey, $learnosityConsumerSecret);

        /* We did it! Exit cleanly with 0
         */
        exit(0);
    }


    /**
     * Return credentials to hit IR APIs, or exits if not found
     *
     * @return array
     */
    private function getIrConfig(): array
    {
        $this->getLogger()->info("Fetching IR API credentials from disk...");

        $irUrl = exec("/usr/local/bin/schoolbox-instance-registry --url 2>/dev/null");
        if (empty($irUrl)) {
            $this->getLogger()->error("IR URL appears invalid: '$irUrl'");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        $puppetCert = exec("/usr/local/bin/schoolbox-instance-registry --puppet-cert 2>/dev/null");
        if (empty($puppetCert)) {
            $this->getLogger()->error("Host puppet cert appears invalid: '$puppetCert'");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        /* We need this to determine which environment we are in
         */
        $siteType = $this->getConfig()->get('site_type');

        /* The Instance Registry API Key is obtained via password manager
         * It's *not* the one in your profile panel in Instance Registry
         */
        $irApiKey = exec("/usr/local/bin/schoolbox-instance-registry --api-key 2>/dev/null");

        if (empty($irApiKey)) {
            // We can't use the Instance::SITE_TYPE_DEVELOPMENT const for two reasons:
            // 1. Need this to reflect the value that was accurate when this version was current
            // 2. vagrant provisioning sets the site_type to current value before executing upgrades.
            if (INSTANCE_ID === 'vagrantx' || in_array($siteType, ['dev', 'vagrant'], true)) {
                $this->getLogger()->debug("No IR API_KEY and instance is development. That's fine. Exiting.");
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }
            $this->getLogger()->error("No IR API_KEY and instance ($siteType) is not development.");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        return [$irUrl, $irApiKey, $siteType];
    }


    /**
     * Fetch Learnosity credentials for this instance from the IR's API
     *
     * @param string $irUrl
     * @param string $irApiKey
     * @param string $siteType
     * @return array
     */
    private function fetchCredentials(string $irUrl, string $irApiKey, string $siteType): array
    {
        $client = new GuzzleHttp\Client(['curl' => Proxy\Server::getInstance()->buildCurlOptions()]);

        /* This endpoint is listed here: https://manage.schoolbox.cloud/api/docs
         */
        $endpoint = sprintf('%s/api/secret/learnosity/%s',
            $irUrl,
            $siteType
        );

        /* Get the credentials from this special endpoint
         */
        $this->getLogger()->info("Fetching Learnosity credentials from IR API...");

        $response = $client->get($endpoint, [
            'headers' => [
                "Authorization" => "Bearer $irApiKey",
                "Content-Type"  => "application/json",
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            $this->getLogger()->error("Unable to fetch instance data from IR: ({$response->getStatusCode()})'{$response->getReasonPhrase()}'");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        /* access-key-id and secret-access-key are in a json-encoded
         * tuple from that special endpoint
         */
        $content = json_decode($response->getBody()->getContents(), false, 512, JSON_THROW_ON_ERROR);

        /* Re-map that again into something easily understood
         */
        return [
            'learnosity_consumer_key' => $content['access-key-id'],
            'learnosity_consumer_secret' => $content['secret-access-key']
        ];
    }


    /**
     * Updates TurnItIn config
     *
     * @param string|null $learnosityConsumerKey
     * @param string|null $learnosityConsumerSecret
     * @return void
     */
    private function updateConfig(?string $learnosityConsumerKey, ?string $learnosityConsumerSecret): void
    {
        $this->getLogger()->info("Updating instance's DB with Learnosity credentials...");

        $irConfig = [
            'learnosity_consumer_key' => $learnosityConsumerKey,
            'learnosity_consumer_secret'  => $learnosityConsumerSecret
        ];

        foreach ($irConfig as $configName => $configValue) {
            $this->getDatabaseInternal()->executeQuery("UPDATE config SET value = :value WHERE name = :name", [
                "value" => $configValue,
                "name"  => $configName,
            ]);
            $this->getLogger()->notice("Updated '$configName' to '$configValue'");
        }
    }


    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return null; // Doesn't matter
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
