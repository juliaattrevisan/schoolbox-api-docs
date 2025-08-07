<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\InstanceRegistry\Credentials;

use Psr\Log\LoggerInterface;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Instance;

class Config
{
    /**
     * Constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(private LoggerInterface $logger)
    {
    }


    /**
     * Return credentials to hit IR APIs, or exits if not found
     *
     * @param string $siteType
     * @return array{string, string} 0: IR URL, 1: IR API key
     */
    public function fetch(string $siteType): array
    {
        $this->logger->info('Fetching Instance Registry API credentials from disk...');

        $irUrl = exec('/usr/local/bin/schoolbox-instance-registry --url 2>/dev/null');
        if (empty($irUrl)) {
            $this->logger->error("IR URL appears invalid: '{$irUrl}'");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        /* The Instance Registry API Key is obtained via password manager
         * It's *not* the one in your profile panel in Instance Registry
         */
        $irApiKey = exec("/usr/local/bin/schoolbox-instance-registry --api-key 2>/dev/null");

        if (empty($irApiKey)) {
            // We can't use the Instance::SITE_TYPE_DEVELOPMENT const for two reasons:
            // 1. Need this to reflect the value that was accurate when this version was current
            // 2. vagrant provisioning sets the site_type to current value before executing upgrades.

            /* Note that in CI, INSTANCE_ID will always be 'ci' and trigger phpstannis
             */
            if (
                in_array(INSTANCE_ID, Instance::DEVELOPMENT_INSTANCE_IDS)
                || in_array($siteType, ['dev', 'vagrant'], true)
            ) {
                $this->logger->debug("No Instance Registry API_KEY and instance is development. That's fine. Exiting.");
                exit(CliKernelAbstract::EXIT_STATUS_OK);
            }

            $this->logger->error("No Instance Registry API_KEY and instance ({$siteType}) is not development.");
            exit(CliKernelAbstract::EXIT_STATUS_APP_ERROR);
        }

        return [$irUrl, $irApiKey];
    }
}
