<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\InstanceRegistry;

use Exception;
use Schoolbox\IConfigLoader;

class InstanceRegistry
{
    /**
     * Schoolbox configuration
     */
    private IConfigLoader $config;

    /**
     * Constructor
     *
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }

    public function getLatestVersionMajor(): string
    {
        $majorVersion = "";
        /**
         * given the full version, everything leading up to the second
         * period is considered the major version
         */
        if (preg_match('/^(\d+\.\d+)\.*/', $this->getLatestVersionFull(), $matches)) {
            $majorVersion = $matches[1];
        }
        return $majorVersion;
    }

    /**
     * get a complete representation of this schoolbox instances version.
     *
     * @example these would look something like:
     *  - 21.0.2-1
     *  - 20.2.11-1
     *  - 20.1.8-2
     *  - 20.0.12-1
     *  - 19.1.12-3
     *  - 18.1.8-1
     *
     * @return string
     */
    public function getLatestVersionFull(): string
    {
        try {
            return (new ApiClient($this->config->get('instance_registry_url')))->getLatestRelease();
        } catch (Exception $e) {
            /**
             * we were unable to fetch the latest release version from the instance registry, so lets
             * fallback to a graceful value that matches the site_version of this instance
             */
            return $this->config->get('site_version');
        }
    }
}
