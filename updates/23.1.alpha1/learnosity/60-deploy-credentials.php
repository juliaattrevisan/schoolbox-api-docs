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
    /** @inheritDoc */
    public function run(): void
    {
        [$irUrl, $puppetCert, $irApiKey] = $this->getIrConfigOrExit();
        [$learnosityConsumerKey, $learnosityConsumerSecret] = $this->fetchLearnosityCredentials($irUrl, $puppetCert, $irApiKey);
        $this->updateLearnosityConfig($learnosityConsumerKey, $learnosityConsumerSecret);
        exit(0);
    }


    /**
     * Return credentials to hit IR APIs, or exits if not found
     *
     * @return array
     */
    private function getIrConfigOrExit(): array
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

        $irApiKey = exec("/usr/local/bin/schoolbox-instance-registry --api-key 2>/dev/null");
        if (empty($irApiKey)) {
            $siteType = $this->getConfig()->get('site_type');
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
        return [$irUrl, $puppetCert, $irApiKey];
    }

    /**
     * Fetch Learnosity credentials for this instance from the IR's API
     *
     * @param string $irUrl
     * @param string $puppetCert
     * @param string $irApiKey
     * @return array
     */
    private function fetchLearnosityCredentials(string $irUrl, string $puppetCert, string $irApiKey): array
    {
        $this->getLogger()->info("Fetching Learnosity credentials from IR API...");

        $client   = new GuzzleHttp\Client(['curl' => Proxy\Server::getInstance()->buildCurlOptions()]);
        $options  = [
            'headers' => [
                "Authorization" => "Bearer $irApiKey",
                "Content-Type"  => "application/json",
            ],
        ];
        $instanceId = INSTANCE_ID;
        $response = $client->get("$irUrl/api/v2/host/$puppetCert/instances?instance_id=$instanceId", $options);

        if ($response->getStatusCode() !== 200) {
            $this->getLogger()->error("Unable to fetch instance data from IR: ({$response->getStatusCode()})'{$response->getReasonPhrase()}'");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }
        $content = $response->getBody()->getContents();

        $instances = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        foreach ($instances["ir_host_instances"] as $instance) {
            if ($instance["instance_id"] !== $instanceId) {
                continue;
            }
            return [$instance["learnosity_consumer_key"], $instance["learnosity_consumer_secret"]];
        }
        $this->getLogger()->error("No data found for instance in API payload");
        exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
    }


    /**
     * Updates TurnItIn config
     *
     * @param string|null $learnosityConsumerKey
     * @param string|null $learnosityConsumerSecret
     * @return void
     */
    private function updateLearnosityConfig(?string $learnosityConsumerKey, ?string $learnosityConsumerSecret): void
    {
        $this->getLogger()->info("Updating instance's DB with Learnosity credentials...");
        $irConfig = [
            "learnosity_consumer_key" => $learnosityConsumerKey,
            "learnosity_consumer_secret"  => $learnosityConsumerSecret
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
